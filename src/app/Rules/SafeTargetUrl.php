<?php

namespace App\Rules;

use App\Models\BlockedDomain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeTargetUrl implements ValidationRule
{
    public const MAX_LENGTH = 2048;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || strlen($value) > self::MAX_LENGTH || preg_match('/[\s\x00-\x1F\x7F]/', $value)) {
            $fail('URL tidak valid.');

            return;
        }

        $parts = parse_url($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($parts === false || ! in_array($scheme, ['http', 'https'], true)) {
            $fail('Hanya URL http atau https yang diizinkan.');

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('URL dengan kredensial tidak diizinkan.');

            return;
        }

        $host = self::normalizeHost((string) ($parts['host'] ?? ''));

        if ($host === null) {
            $fail('Host URL tidak valid.');

            return;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail('Alamat IP privat atau lokal tidak diizinkan.');
            }

            return;
        }

        // Hostname must have a dot and a non-numeric TLD (blocks localhost, decimal IPs).
        if (! str_contains($host, '.') || preg_match('/\.\d+$|^[\d.]+$|^0x/i', $host) || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            $fail('Host URL tidak valid.');

            return;
        }

        $appHost = self::normalizeHost((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($appHost !== null && ($host === $appHost || str_ends_with($host, '.'.$appHost))) {
            $fail('Tidak bisa memendekkan link ke situs ini sendiri.');

            return;
        }

        if (BlockedDomain::query()->whereIn('domain', self::hostSuffixes($host))->exists()) {
            $fail('Domain tujuan diblokir.');
        }
    }

    // Lowercase punycode host without brackets/trailing dot, or null when invalid.
    public static function normalizeHost(string $host): ?string
    {
        $host = rtrim(trim(strtolower($host), '[]'), '.');

        if ($host === '') {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        if (preg_match('/[^\x20-\x7E]/', $host)) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if ($ascii === false) {
                return null;
            }

            $host = strtolower($ascii);
        }

        if (strlen($host) > 253 || ! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*$/', $host)) {
            return null;
        }

        return $host;
    }

    // a.b.example.com → [a.b.example.com, b.example.com, example.com, com]
    public static function hostSuffixes(string $host): array
    {
        $labels = explode('.', $host);
        $suffixes = [];

        for ($i = 0, $n = count($labels); $i < $n; $i++) {
            $suffixes[] = implode('.', array_slice($labels, $i));
        }

        return $suffixes;
    }
}
