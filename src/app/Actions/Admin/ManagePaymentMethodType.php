<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\SwapsSortOrder;
use App\Enums\PermissionName;
use App\Models\PaymentMethodType;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// Master data of payment method types: authorized and audited.
class ManagePaymentMethodType
{
    use SwapsSortOrder;

    public const CODE_MAX = 30;

    public const NAME_MAX = 40;

    public function __construct(protected AuditLogger $audit) {}

    public function create(User $admin, string $code, string $name, ?string $ip = null): PaymentMethodType
    {
        $data = Validator::make(['code' => strtolower(trim($code)), 'name' => trim($name)], [
            'code' => ['required', 'string', 'max:'.self::CODE_MAX, 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/'],
            'name' => $this->nameRules(),
        ], ['code.regex' => 'Kode hanya huruf kecil, angka, dan tanda - atau _.'], ['code' => 'kode', 'name' => 'nama'])->validate();

        return $this->change($admin, function () use ($admin, $data, $ip) {
            if (PaymentMethodType::query()->where('code', $data['code'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode tipe sudah dipakai.']);
            }

            try {
                $type = PaymentMethodType::create($data + ['sort' => (int) PaymentMethodType::query()->max('sort') + 1]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['code' => 'Kode tipe sudah dipakai.']);
            }

            $this->audit->log($admin, 'payment_method_type.create', $type, $data, null, $ip);

            return $type;
        });
    }

    public function update(User $admin, string $id, string $name, ?string $ip = null): void
    {
        $name = trim($name);

        Validator::make(['name' => $name], ['name' => $this->nameRules()], [], ['name' => 'nama'])->validate();

        $this->change($admin, function () use ($admin, $id, $name, $ip) {
            $type = $this->locked($id);

            if ($type->name === $name) {
                return;
            }

            $this->audit->log($admin, 'payment_method_type.update', $type, ['name' => [$type->name, $name]], null, $ip);
            $type->update(['name' => $name]);
        });
    }

    public function move(User $admin, string $id, bool $up, ?string $ip = null): void
    {
        $this->change($admin, function () use ($admin, $id, $up, $ip) {
            $type = $this->locked($id);
            $all = PaymentMethodType::query()->orderBy('sort')->orderBy('id')->lockForUpdate()->get();

            if ($this->swapWithNeighbour($all, $type, $up)) {
                $this->audit->log($admin, 'payment_method_type.move', $type, ['code' => $type->code, 'direction' => $up ? 'up' : 'down'], null, $ip);
            }
        });
    }

    // Refused while methods still use the type (the FK restricts it too).
    public function delete(User $admin, string $id, ?string $ip = null): void
    {
        $this->change($admin, function () use ($admin, $id, $ip) {
            $type = $this->locked($id);
            $used = $type->methods()->lockForUpdate()->count();

            if ($used > 0) {
                throw ValidationException::withMessages(['type' => "Tipe masih dipakai {$used} metode. Pindahkan metodenya dulu."]);
            }

            $this->audit->log($admin, 'payment_method_type.delete', $type, ['code' => $type->code, 'name' => $type->name], null, $ip);
            $type->delete();
        });
    }

    /**
     * @return list<string>
     */
    private function nameRules(): array
    {
        return ['required', 'string', 'max:'.self::NAME_MAX, 'regex:/^[^\p{C}]+$/u'];
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

        return DB::transaction($change);
    }

    private function locked(string $id): PaymentMethodType
    {
        return PaymentMethodType::query()->whereKey($id)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['type' => 'Tipe metode tidak ditemukan.']);
    }
}
