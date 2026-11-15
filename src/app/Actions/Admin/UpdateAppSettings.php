<?php

namespace App\Actions\Admin;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Settings\SettingRegistry;
use App\Services\Settings\SettingsRepository;
use App\Support\PasswordConfirmation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateAppSettings
{
    public function __construct(
        protected SettingRegistry $registry,
        protected SettingsRepository $settings,
        protected AuditLogger $audit,
    ) {}

    /**
     * Inputs may be nested (['mail' => ['host' => ..]]) or dotted; unknown keys are ignored.
     *
     * @param  array<string, mixed>  $values  non-secret; empty = remove (use .env)
     * @param  array<string, mixed>  $secrets  empty = unchanged
     * @param  array<string, mixed>  $clear  secret key => true removes the stored value
     * @return array<string, mixed> audit changes
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, array $values, array $secrets, array $clear, ?string $ip = null): array
    {
        Gate::forUser($actor)->authorize('settings.manage');

        if (! PasswordConfirmation::recent()) {
            throw new AuthorizationException('Konfirmasi password diperlukan.');
        }

        $values = Arr::dot($values);
        $secrets = Arr::dot($secrets);
        $clear = Arr::dot($clear);

        $data = [];
        $rules = [];
        $attributes = [];
        $writes = [];

        foreach ($this->registry->all() as $key => $item) {
            if ($item['type'] === 'secret') {
                if (filter_var($clear[$key] ?? false, FILTER_VALIDATE_BOOL)) {
                    $writes[$key] = null;

                    continue;
                }

                $secret = $secrets[$key] ?? null;

                if (! is_string($secret) || $secret === '') {
                    continue;
                }

                $path = "secrets.{$key}";
                $writes[$key] = $secret;
            } else {
                if (! array_key_exists($key, $values)) {
                    continue;
                }

                $value = $values[$key];
                $value = is_string($value) ? trim($value) : $value;
                $path = "values.{$key}";
                $writes[$key] = ($value === null || $value === '') ? null : $value;

                if ($writes[$key] === null) {
                    continue;
                }
            }

            data_set($data, $path, $writes[$key]);
            $rules[$path] = ['required', ...$item['rules']];
            $attributes[$path] = $item['label'];
        }

        Validator::make($data, $rules, [], $attributes)->validate();

        $changes = $this->persist($actor, $writes, $ip);

        if ($changes === []) {
            return [];
        }

        $this->settings->forget();
        // Same signal as queue:restart so workers reload config.
        Cache::forever('illuminate:queue:restart', now()->getTimestamp());

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $writes  key => new value (null = delete)
     * @return array<string, mixed>
     */
    protected function persist(User $actor, array $writes, ?string $ip): array
    {
        return DB::transaction(function () use ($actor, $writes, $ip) {
            $existing = AppSetting::query()->whereIn('key', array_keys($writes))->get()->keyBy('key');
            $changes = [];
            $subject = null;

            foreach ($writes as $key => $new) {
                $secret = $this->registry->isSecret($key);
                $row = $existing->get($key);
                [$old, $corrupt] = $this->readOld($key, $row);
                $new = $this->registry->cast($key, $new);

                if (! $corrupt && $old === $new) {
                    continue;
                }

                if ($new === null) {
                    $row?->delete();
                    $subject ??= $row;
                } else {
                    // Undecryptable row (APP_KEY changed): replace, dirty check would decrypt it.
                    if ($corrupt) {
                        $row->delete();
                    }

                    // Model path keeps the encrypted cast (no upsert/query builder).
                    $saved = AppSetting::updateOrCreate(['key' => $key], ['value' => (string) (is_bool($new) ? (int) $new : $new)]);
                    $subject ??= $saved;
                }

                $changes[$key] = $secret
                    ? ($new === null ? 'cleared' : 'changed')
                    : ['old' => $corrupt ? 'undecryptable' : $old, 'new' => $new];
            }

            if ($changes !== [] && $subject !== null) {
                $this->audit->log($actor, 'settings.updated', $subject, $changes, null, $ip);
            }

            return $changes;
        });
    }

    /**
     * @return array{0: mixed, 1: bool} [old value, undecryptable]
     */
    protected function readOld(string $key, ?AppSetting $row): array
    {
        $cipher = $row?->getRawOriginal('value');

        if ($cipher === null) {
            return [null, false];
        }

        try {
            return [$this->registry->cast($key, Crypt::decryptString($cipher)), false];
        } catch (DecryptException) {
            return [null, true];
        }
    }
}
