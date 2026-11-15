<?php

namespace App\Services\Settings;

use App\Models\AppSetting;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

// Reads app_settings (ciphertext cached, never plaintext on disk) and overlays config.
class SettingsRepository
{
    public const CACHE_KEY = 'app_settings.v1';

    public const MASK = '••••••••';

    /** @var array<string, string>|null key => plaintext */
    protected ?array $decrypted = null;

    /** @var array<string, mixed> config values before override (.env) */
    protected array $envValues = [];

    protected bool $decryptFailed = false;

    public function __construct(protected SettingRegistry $registry) {}

    /**
     * @return array<string, string> key => ciphertext
     */
    public function raw(): array
    {
        $cached = $this->cache()->get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            // toBase() skips the encrypted cast: ciphertext only.
            $rows = AppSetting::query()
                ->whereIn('key', $this->registry->keys())
                ->whereNotNull('value')
                ->toBase()
                ->pluck('value', 'key')
                ->all();
        } catch (QueryException) {
            // Fresh install / before migrate: not cached.
            return [];
        }

        $this->cache()->forever(self::CACHE_KEY, $rows);

        return $rows;
    }

    public function applyToConfig(): void
    {
        // Never bake DB secrets into bootstrap/cache/config.php.
        if ($this->cachingConfig()) {
            return;
        }

        foreach ($this->registry->all() as $key => $item) {
            if (! array_key_exists($key, $this->envValues)) {
                $this->envValues[$key] = config($item['config']);
            }

            $value = $this->get($key);

            if ($value !== null && $value !== '') {
                config()->set($item['config'], $value);
            }
        }
    }

    // Stored (DB) value cast to its type; null when not stored.
    public function get(string $key): mixed
    {
        if (! $this->registry->has($key)) {
            return null;
        }

        return $this->registry->cast($key, $this->values()[$key] ?? null);
    }

    // Config value before DB override.
    public function envValue(string $key): mixed
    {
        $item = $this->registry->find($key);

        if ($item === null) {
            return null;
        }

        return array_key_exists($key, $this->envValues) ? $this->envValues[$key] : config($item['config']);
    }

    /**
     * @return 'db'|'env'|'empty'
     */
    public function source(string $key): string
    {
        if (! $this->blank($this->get($key))) {
            return 'db';
        }

        return $this->blank($this->envValue($key)) ? 'empty' : 'env';
    }

    // Masked effective value (DB, else .env); null when empty.
    public function masked(string $key): ?string
    {
        $value = match ($this->source($key)) {
            'db' => (string) $this->get($key),
            'env' => (string) $this->envValue($key),
            default => null,
        };

        if ($value === null) {
            return null;
        }

        return mb_strlen($value) >= 12 ? self::MASK.mb_substr($value, -4) : self::MASK;
    }

    public function hasDecryptErrors(): bool
    {
        $this->values();

        return $this->decryptFailed;
    }

    public function forget(): void
    {
        $this->cache()->forget(self::CACHE_KEY);
        $this->decrypted = null;
        $this->decryptFailed = false;
    }

    /**
     * @return array<string, string>
     */
    protected function values(): array
    {
        if ($this->decrypted !== null) {
            return $this->decrypted;
        }

        $this->decrypted = [];

        foreach ($this->raw() as $key => $cipher) {
            if (! $this->registry->has($key) || ! is_string($cipher)) {
                continue;
            }

            try {
                // Same format as the "encrypted" cast (unserialized string).
                $this->decrypted[$key] = Crypt::decryptString($cipher);
            } catch (DecryptException $e) {
                $this->decryptFailed = true;
                report($e);
            }
        }

        return $this->decrypted;
    }

    // Like runningConsoleCommand(), but also matches when options precede the command.
    protected function cachingConfig(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        return array_intersect(array_slice($_SERVER['argv'] ?? [], 1), ['config:cache', 'optimize']) !== [];
    }

    protected function blank(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    protected function cache(): CacheRepository
    {
        return Cache::store(config('toolkits.settings_cache_store', 'file'));
    }
}
