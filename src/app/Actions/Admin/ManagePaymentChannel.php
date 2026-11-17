<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\SwapsSortOrder;
use App\Enums\AdminResource;
use App\Enums\PaymentChannelKind;
use App\Enums\PermissionAction;
use App\Models\PaymentChannel;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Billing\PaymentChannelCatalog;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Master data of payment channels: authorized, audited, and invalidates the channel catalog.
// Code and kind are fixed after creation: payments store the code, and the kind decides the gateway flow.
class ManagePaymentChannel
{
    use SwapsSortOrder;

    public const CODE_MAX = 20;

    public const GATEWAY_CODE_MAX = 60;

    public const NAME_MAX = 40;

    public const EXPIRY_MIN = 5;

    public const EXPIRY_MAX = 10080;

    // Fields an admin may change after creation.
    private const EDITABLE = ['gateway_code', 'name', 'color', 'needs_mobile_number', 'fixed_expiry_minutes'];

    public function __construct(protected AuditLogger $audit, protected PaymentChannelCatalog $catalog) {}

    /**
     * @param  array{code: string, gateway_code: string, name: string, kind: string, color: string, needs_mobile_number: bool, fixed_expiry_minutes: ?int}  $input
     */
    public function create(User $admin, array $input, ?string $ip = null): PaymentChannel
    {
        $data = $this->validated($input, true);

        return $this->change($admin, PermissionAction::Create, function () use ($admin, $data, $ip) {
            if (PaymentChannel::query()->where('code', $data['code'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['code' => 'Kode channel sudah dipakai.']);
            }

            try {
                $channel = PaymentChannel::create($data + ['sort' => (int) PaymentChannel::query()->max('sort') + 1]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['code' => 'Kode channel sudah dipakai.']);
            }

            $this->audit->log($admin, 'payment_channel.create', $channel, $data, null, $ip);

            return $channel;
        });
    }

    /**
     * @param  array{gateway_code: string, name: string, color: string, needs_mobile_number: bool, fixed_expiry_minutes: ?int}  $input
     */
    public function update(User $admin, string $id, array $input, ?string $ip = null): void
    {
        $this->change($admin, PermissionAction::Update, function () use ($admin, $id, $input, $ip) {
            $channel = $this->locked($id);
            $data = $this->validated($input + ['kind' => $channel->kind->value], false);
            $changes = [];

            foreach (self::EDITABLE as $field) {
                if ($channel->{$field} !== $data[$field]) {
                    $changes[$field] = [$channel->{$field}, $data[$field]];
                }
            }

            if ($changes === []) {
                return;
            }

            $this->audit->log($admin, 'payment_channel.update', $channel, $changes, null, $ip);
            $channel->update(array_intersect_key($data, array_flip(self::EDITABLE)));
        });
    }

    public function move(User $admin, string $id, bool $up, ?string $ip = null): void
    {
        $this->change($admin, PermissionAction::Update, function () use ($admin, $id, $up, $ip) {
            $channel = $this->locked($id);
            $all = PaymentChannel::query()->orderBy('sort')->orderBy('id')->lockForUpdate()->get();

            if ($this->swapWithNeighbour($all, $channel, $up)) {
                $this->audit->log($admin, 'payment_channel.move', $channel, ['code' => $channel->code, 'direction' => $up ? 'up' : 'down'], null, $ip);
            }
        });
    }

    // Refused while a payment method offers the channel (the FK restricts it too). Payments keep the code as history.
    public function delete(User $admin, string $id, ?string $ip = null): void
    {
        $this->change($admin, PermissionAction::Delete, function () use ($admin, $id, $ip) {
            $channel = $this->locked($id);

            if ($channel->methods()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['channel' => 'Channel masih dipakai metode pembayaran. Hapus metodenya dulu.']);
            }

            $this->audit->log($admin, 'payment_channel.delete', $channel, ['code' => $channel->code, 'name' => $channel->name], null, $ip);
            $channel->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validated(array $input, bool $creating): array
    {
        $kind = PaymentChannelKind::tryFrom((string) ($input['kind'] ?? ''));
        $expiry = $input['fixed_expiry_minutes'] ?? null;

        $data = [
            'gateway_code' => strtoupper(trim((string) ($input['gateway_code'] ?? ''))),
            'name' => trim((string) ($input['name'] ?? '')),
            'color' => strtolower(trim((string) ($input['color'] ?? ''))),
            // Only push-notification e-wallets ask for a number.
            'needs_mobile_number' => $kind === PaymentChannelKind::EWallet && ($input['needs_mobile_number'] ?? false) === true,
            'fixed_expiry_minutes' => $expiry === null || $expiry === '' ? null : $expiry,
        ];

        $rules = [
            'gateway_code' => ['required', 'string', 'max:'.self::GATEWAY_CODE_MAX, 'regex:/^[A-Z0-9]+(?:_[A-Z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, 'regex:/^[^\p{C}]+$/u'],
            'color' => ['required', 'regex:/^#[0-9a-f]{6}$/'],
            'fixed_expiry_minutes' => ['nullable', 'integer', 'between:'.self::EXPIRY_MIN.','.self::EXPIRY_MAX],
        ];

        if ($creating) {
            $data = ['code' => strtoupper(trim((string) ($input['code'] ?? ''))), 'kind' => (string) ($input['kind'] ?? '')] + $data;
            $rules += [
                'code' => ['required', 'string', 'max:'.self::CODE_MAX, 'regex:/^[A-Z0-9]+(?:_[A-Z0-9]+)*$/'],
                'kind' => ['required', Rule::enum(PaymentChannelKind::class)],
            ];
        }

        $valid = Validator::make($data, $rules, [
            'code.regex' => 'Kode hanya huruf, angka, dan garis bawah.',
            'gateway_code.regex' => 'Kode gateway hanya huruf, angka, dan garis bawah.',
            'color.regex' => 'Warna harus berformat #rrggbb.',
        ], [
            'code' => 'kode',
            'gateway_code' => 'kode gateway',
            'name' => 'nama',
            'kind' => 'jenis',
            'color' => 'warna',
            'fixed_expiry_minutes' => 'masa berlaku',
        ])->validate();

        $valid['needs_mobile_number'] = $data['needs_mobile_number'];
        $valid['fixed_expiry_minutes'] = $valid['fixed_expiry_minutes'] === null ? null : (int) $valid['fixed_expiry_minutes'];

        return $valid;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    private function change(User $admin, PermissionAction $action, Closure $change): mixed
    {
        Gate::forUser($admin)->authorize(AdminResource::PaymentChannels->permission($action));

        try {
            return DB::transaction($change);
        } finally {
            $this->catalog->forget();
        }
    }

    private function locked(string $id): PaymentChannel
    {
        return PaymentChannel::query()->whereKey($id)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['channel' => 'Payment channel tidak ditemukan.']);
    }
}
