<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\SwapsSortOrder;
use App\Enums\AdminResource;
use App\Enums\PermissionAction;
use App\Exceptions\FileProcessingException;
use App\Models\PaymentChannel;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodType;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Billing\PaymentMethodCatalog;
use App\Services\Files\ImageProcessor;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

// Every change to payment methods: authorized, audited, and invalidates the checkout catalog.
class ManagePaymentMethod
{
    use SwapsSortOrder;

    public const LOGO_MAX_KB = 512;

    public const LOGO_MIMES = ['png', 'jpg', 'jpeg', 'webp'];

    // Longest side of the stored logo; enough for the checkout tile on high-density screens.
    private const LOGO_SIDE = 256;

    public function __construct(
        protected AuditLogger $audit,
        protected PaymentMethodCatalog $catalog,
        protected ImageProcessor $images,
    ) {}

    // Adds a channel that is not offered yet (inactive until switched on).
    // Null type = the type matching the channel kind, when it exists. The display name comes from the channel.
    public function add(User $admin, string $channel, ?string $typeId = null, ?string $ip = null, ?UploadedFile $logo = null): PaymentMethod
    {
        return $this->withLogo($admin, PermissionAction::Create, $logo, function (?string $logoPath) use ($admin, $channel, $typeId, $ip) {
            // Locked so the channel cannot be deleted before the method is saved.
            $value = PaymentChannel::query()->where('code', $channel)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['channel' => 'Channel tidak dikenal.']);

            if (PaymentMethod::query()->where('channel', $value->code)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['channel' => 'Metode ini sudah ada.']);
            }

            $method = PaymentMethod::create([
                'channel' => $value->code,
                'payment_method_type_id' => $typeId === null
                    ? PaymentMethodType::query()->where('code', $value->kind->value)->lockForUpdate()->value('id')
                    : $this->existingType($typeId),
                'logo_path' => $logoPath,
                'is_active' => false,
                'sort' => (int) PaymentMethod::query()->max('sort') + 1,
            ]);

            $this->audit->log($admin, 'payment_method.create', $method, array_filter(['channel' => $value->code, 'logo' => $logoPath]), null, $ip);

            return [$method, null];
        });
    }

    // Null $typeId = no type; null $logo keeps the current logo. The channel itself never changes.
    public function update(User $admin, string $id, ?string $typeId, ?string $ip = null, ?UploadedFile $logo = null): void
    {
        $this->withLogo($admin, PermissionAction::Update, $logo, function (?string $logoPath) use ($admin, $id, $typeId, $ip) {
            $method = $this->locked($id);
            $typeId = $typeId === null ? null : $this->existingType($typeId);
            $old = $method->logo_path;
            $changes = array_filter([
                'type' => $method->payment_method_type_id !== $typeId ? [$method->payment_method_type_id, $typeId] : null,
                'logo' => $logoPath !== null ? [$old, $logoPath] : null,
            ]);

            if ($changes === []) {
                return [null, null];
            }

            $this->audit->log($admin, 'payment_method.update', $method, $changes, null, $ip);
            $method->update(['payment_method_type_id' => $typeId, 'logo_path' => $logoPath ?? $old]);

            return [null, $logoPath !== null ? $old : null];
        });
    }

    // Back to the text badge in checkout.
    public function removeLogo(User $admin, string $id, ?string $ip = null): void
    {
        $this->withLogo($admin, PermissionAction::Update, null, function () use ($admin, $id, $ip) {
            $method = $this->locked($id);
            $old = $method->logo_path;

            if ($old === null) {
                return [null, null];
            }

            $method->update(['logo_path' => null]);
            $this->audit->log($admin, 'payment_method.update', $method, ['logo' => [$old, null]], null, $ip);

            return [null, $old];
        });
    }

    public function setActive(User $admin, string $id, bool $active, ?string $ip = null): void
    {
        $this->change($admin, PermissionAction::Update, function () use ($admin, $id, $active, $ip) {
            $method = $this->locked($id);

            if ($method->is_active === $active) {
                return;
            }

            $method->update(['is_active' => $active]);
            $this->audit->log($admin, 'payment_method.toggle', $method, ['channel' => $method->channel, 'active' => $active], null, $ip);
        });
    }

    // Swaps the order with the neighbour in the full list (checkout keeps this order within each channel kind).
    public function move(User $admin, string $id, bool $up, ?string $ip = null): void
    {
        $this->change($admin, PermissionAction::Update, function () use ($admin, $id, $up, $ip) {
            $method = $this->locked($id);
            $all = PaymentMethod::query()->orderBy('sort')->orderBy('id')->lockForUpdate()->get();

            if (! $this->swapWithNeighbour($all, $method, $up)) {
                return;
            }

            $this->audit->log($admin, 'payment_method.move', $method, ['channel' => $method->channel, 'direction' => $up ? 'up' : 'down'], null, $ip);
        });
    }

    // Payments keep their own channel code, so history is unaffected.
    public function delete(User $admin, string $id, ?string $ip = null): void
    {
        $this->withLogo($admin, PermissionAction::Delete, null, function () use ($admin, $id, $ip) {
            $method = $this->locked($id);

            $this->audit->log($admin, 'payment_method.delete', $method, ['channel' => $method->channel], null, $ip);
            $method->delete();

            return [null, $method->logo_path];
        });
    }

    /**
     * Stores the new logo before the change and removes whichever file ends up unused: the new one when the change fails,
     * the replaced one once it is committed.
     *
     * @template T
     *
     * @param  Closure(?string): array{0: T, 1: ?string}  $change  returns [result, path of the logo it replaced]
     * @return T
     */
    private function withLogo(User $admin, PermissionAction $action, ?UploadedFile $logo, Closure $change): mixed
    {
        Gate::forUser($admin)->authorize(AdminResource::PaymentMethods->permission($action));

        $path = $logo === null ? null : $this->storeLogo($logo);

        try {
            [$result, $replaced] = $this->change($admin, $action, fn () => $change($path));
        } catch (Throwable $e) {
            $this->deleteLogo($path);

            throw $e;
        }

        $this->deleteLogo($replaced);

        return $result;
    }

    // Accepts real PNG/JPEG/WebP only (no SVG, which can carry script); stored re-encoded under a random name.
    private function storeLogo(UploadedFile $file): string
    {
        Validator::make(['logo' => $file], [
            'logo' => ['required', 'file', 'image', 'mimes:'.implode(',', self::LOGO_MIMES), 'max:'.self::LOGO_MAX_KB],
        ], [], ['logo' => 'logo'])->validate();

        $tmp = tempnam(sys_get_temp_dir(), 'logo');

        try {
            $this->images->logo($file->getRealPath(), $tmp, self::LOGO_SIDE);
            $path = PaymentMethod::LOGO_DIRECTORY.'/'.Str::uuid7().'.webp';

            if (! Storage::disk(PaymentMethod::LOGO_DISK)->put($path, (string) file_get_contents($tmp))) {
                throw ValidationException::withMessages(['logo' => 'Logo gagal disimpan. Coba lagi.']);
            }

            return $path;
        } catch (FileProcessingException $e) {
            throw ValidationException::withMessages(['logo' => $e->getMessage()]);
        } finally {
            @unlink($tmp);
        }
    }

    private function deleteLogo(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(PaymentMethod::LOGO_DISK)->delete($path);
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    private function change(User $admin, PermissionAction $action, Closure $change): mixed
    {
        Gate::forUser($admin)->authorize(AdminResource::PaymentMethods->permission($action));

        try {
            return DB::transaction($change);
        } finally {
            $this->catalog->forget();
        }
    }

    // Locked so the type cannot be deleted before the method is saved.
    private function existingType(string $typeId): string
    {
        return PaymentMethodType::query()->whereKey($typeId)->lockForUpdate()->value('id')
            ?? throw ValidationException::withMessages(['type' => 'Tipe metode tidak ditemukan.']);
    }

    private function locked(string $id): PaymentMethod
    {
        return PaymentMethod::query()->whereKey($id)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['method' => 'Metode pembayaran tidak ditemukan.']);
    }
}
