<?php

namespace App\Actions\Admin;

use App\Enums\PaymentChannel;
use App\Enums\PermissionName;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Billing\PaymentMethodCatalog;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// Every change to payment methods: authorized, audited, and invalidates the checkout catalog.
class ManagePaymentMethod
{
    public const NAME_MAX = 40;

    public function __construct(protected AuditLogger $audit, protected PaymentMethodCatalog $catalog) {}

    // Adds a catalog channel that is not offered yet (inactive until switched on).
    public function add(User $admin, string $channel, ?string $ip = null): PaymentMethod
    {
        $value = PaymentChannel::tryFrom($channel) ?? throw ValidationException::withMessages(['channel' => 'Metode tidak dikenal.']);

        return $this->change($admin, function () use ($admin, $value, $ip) {
            if (PaymentMethod::query()->where('channel', $value->value)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['channel' => 'Metode ini sudah ada.']);
            }

            $method = PaymentMethod::create([
                'channel' => $value,
                'name' => $value->fullLabel(),
                'is_active' => false,
                'sort' => (int) PaymentMethod::query()->max('sort') + 1,
            ]);

            $this->audit->log($admin, 'payment_method.create', $method, ['channel' => $value->value], null, $ip);

            return $method;
        });
    }

    public function rename(User $admin, string $id, string $name, ?string $ip = null): void
    {
        $name = trim($name);

        Validator::make(['name' => $name], [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, 'regex:/^[^\p{C}]+$/u'],
        ], [], ['name' => 'nama'])->validate();

        $this->change($admin, function () use ($admin, $id, $name, $ip) {
            $method = $this->locked($id);

            if ($method->name === $name) {
                return;
            }

            $this->audit->log($admin, 'payment_method.update', $method, ['name' => [$method->name, $name]], null, $ip);
            $method->update(['name' => $name]);
        });
    }

    public function setActive(User $admin, string $id, bool $active, ?string $ip = null): void
    {
        $this->change($admin, function () use ($admin, $id, $active, $ip) {
            $method = $this->locked($id);

            if ($method->is_active === $active) {
                return;
            }

            $method->update(['is_active' => $active]);
            $this->audit->log($admin, 'payment_method.toggle', $method, ['channel' => $method->channel->value, 'active' => $active], null, $ip);
        });
    }

    // Swaps the order with the neighbour of the same kind (the checkout groups methods by kind).
    public function move(User $admin, string $id, bool $up, ?string $ip = null): void
    {
        $this->change($admin, function () use ($admin, $id, $up, $ip) {
            $method = $this->locked($id);
            $siblings = PaymentMethod::query()->orderBy('sort')->orderBy('id')->lockForUpdate()->get()
                ->filter(fn (PaymentMethod $m) => $m->channel->kind() === $method->channel->kind())
                ->values();
            $index = $siblings->search(fn (PaymentMethod $m) => $m->is($method));
            $neighbour = $siblings->get($up ? $index - 1 : $index + 1);

            if ($neighbour === null) {
                return;
            }

            [$a, $b] = [$method->sort, $neighbour->sort];
            // Equal sort values would make the swap a no-op.
            $method->update(['sort' => $a === $b ? $b + ($up ? -1 : 1) : $b]);
            $neighbour->update(['sort' => $a]);

            $this->audit->log($admin, 'payment_method.move', $method, ['channel' => $method->channel->value, 'direction' => $up ? 'up' : 'down'], null, $ip);
        });
    }

    // Payments keep their own channel code, so history is unaffected.
    public function delete(User $admin, string $id, ?string $ip = null): void
    {
        $this->change($admin, function () use ($admin, $id, $ip) {
            $method = $this->locked($id);

            $this->audit->log($admin, 'payment_method.delete', $method, ['channel' => $method->channel->value, 'name' => $method->name], null, $ip);
            $method->delete();
        });
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    private function change(User $admin, Closure $change): mixed
    {
        Gate::forUser($admin)->authorize(PermissionName::PaymentMethodsManage->value);

        try {
            return DB::transaction($change);
        } finally {
            $this->catalog->forget();
        }
    }

    private function locked(string $id): PaymentMethod
    {
        return PaymentMethod::query()->whereKey($id)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['method' => 'Metode pembayaran tidak ditemukan.']);
    }
}
