<?php

namespace App\Services\Settings;

use Closure;

// Whitelist of DB-overridable settings; keys outside this list are never read or written.
class SettingRegistry
{
    public const GROUPS = [
        'general' => 'Umum',
        'mail' => 'Email (SMTP)',
        'google' => 'Login Google',
        'xendit' => 'Xendit',
    ];

    /**
     * @return array<string, array{key: string, group: string, label: string, type: string, rules: array, options: array|null, config: string, help: string|null}>
     */
    public function all(): array
    {
        $items = [];

        foreach ($this->definitions() as $key => $def) {
            $items[$key] = [
                'key' => $key,
                'group' => explode('.', $key, 2)[0],
                'label' => $def['label'],
                'type' => $def['type'],
                'rules' => $def['rules'],
                'options' => $def['options'] ?? null,
                'config' => $def['config'],
                'help' => $def['help'] ?? null,
            ];
        }

        return $items;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->definitions());
    }

    /**
     * @return array{key: string, group: string, label: string, type: string, rules: array, options: array|null, config: string, help: string|null}|null
     */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->definitions());
    }

    public function isSecret(string $key): bool
    {
        return ($this->definitions()[$key]['type'] ?? null) === 'secret';
    }

    /**
     * @return array<string, array<string, array>>
     */
    public function grouped(): array
    {
        $groups = array_fill_keys(array_keys(self::GROUPS), []);

        foreach ($this->all() as $key => $item) {
            $groups[$item['group']][$key] = $item;
        }

        return $groups;
    }

    // Cast a stored string to the setting's type.
    public function cast(string $key, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($this->definitions()[$key]['type'] ?? 'string') {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => (string) $value,
        };
    }

    /**
     * @return array<string, array{label: string, type: string, rules: array, config: string, options?: array, help?: string}>
     */
    protected function definitions(): array
    {
        return [
            'general.site_name' => [
                'label' => 'Nama situs',
                'type' => 'string',
                'rules' => ['string', 'max:60'],
                'config' => 'app.name',
            ],
            'general.support_email' => [
                'label' => 'Email dukungan',
                'type' => 'email',
                'rules' => ['string', 'max:255', 'email:rfc'],
                'config' => 'toolkits.support_email',
            ],
            'mail.mailer' => [
                'label' => 'Mailer',
                'type' => 'select',
                'options' => ['smtp' => 'SMTP', 'log' => 'Log (tidak mengirim)'],
                'rules' => ['string', 'in:smtp,log'],
                'config' => 'mail.default',
            ],
            'mail.host' => [
                'label' => 'SMTP host',
                'type' => 'string',
                'rules' => ['string', 'max:255', 'regex:/^(?=.{1,253}$)[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/'],
                'config' => 'mail.mailers.smtp.host',
                'help' => 'Hostname tanpa skema, mis. smtp.example.com.',
            ],
            'mail.port' => [
                'label' => 'SMTP port',
                'type' => 'int',
                'rules' => ['integer', 'min:1', 'max:65535'],
                'config' => 'mail.mailers.smtp.port',
            ],
            'mail.scheme' => [
                'label' => 'SMTP scheme',
                'type' => 'select',
                'options' => ['smtp' => 'smtp (STARTTLS)', 'smtps' => 'smtps (TLS)'],
                'rules' => ['string', 'in:smtp,smtps'],
                'config' => 'mail.mailers.smtp.scheme',
            ],
            'mail.username' => [
                'label' => 'SMTP username',
                'type' => 'string',
                'rules' => ['string', 'max:255'],
                'config' => 'mail.mailers.smtp.username',
            ],
            'mail.password' => [
                'label' => 'SMTP password',
                'type' => 'secret',
                'rules' => ['string', 'max:255'],
                'config' => 'mail.mailers.smtp.password',
            ],
            'mail.from_address' => [
                'label' => 'Alamat pengirim',
                'type' => 'email',
                'rules' => ['string', 'max:255', 'email'],
                'config' => 'mail.from.address',
            ],
            'mail.from_name' => [
                'label' => 'Nama pengirim',
                'type' => 'string',
                'rules' => ['string', 'max:100'],
                'config' => 'mail.from.name',
            ],
            'google.client_id' => [
                'label' => 'Client ID',
                'type' => 'string',
                'rules' => ['string', 'max:255'],
                'config' => 'services.google.client_id',
            ],
            'google.client_secret' => [
                'label' => 'Client secret',
                'type' => 'secret',
                'rules' => ['string', 'max:255'],
                'config' => 'services.google.client_secret',
            ],
            'google.redirect' => [
                'label' => 'Redirect URI',
                'type' => 'url',
                'rules' => ['string', 'max:255', 'url', $this->googleRedirectRule()],
                'config' => 'services.google.redirect',
                'help' => 'Harus https dan sama dengan host aplikasi, path /auth/google/callback.',
            ],
            'xendit.secret_key' => [
                'label' => 'Secret key',
                'type' => 'secret',
                'rules' => ['string', 'max:255', 'regex:/^xnd_(development|production)_/'],
                'config' => 'services.xendit.secret_key',
            ],
            'xendit.callback_token' => [
                'label' => 'Callback token',
                'type' => 'secret',
                'rules' => ['string', 'min:20', 'max:255'],
                'config' => 'services.xendit.callback_token',
            ],
            'xendit.expiry_minutes' => [
                'label' => 'Masa berlaku pembayaran (menit)',
                'type' => 'int',
                'rules' => ['integer', 'min:5', 'max:10080'],
                'config' => 'services.xendit.expiry_minutes',
                'help' => 'Untuk QRIS dan Virtual Account (maks. 10080 = 7 hari). Masa berlaku e-wallet ditentukan penyedianya.',
            ],
        ];
    }

    protected function googleRedirectRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $parts = parse_url((string) $value);
            $scheme = strtolower($parts['scheme'] ?? '');
            $allowHttp = app()->environment('local');
            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

            if (! ($scheme === 'https' || ($allowHttp && $scheme === 'http'))) {
                $fail('Redirect URI harus memakai https.');

                return;
            }

            if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                $fail('Redirect URI tidak valid.');

                return;
            }

            if ($appHost === '' || strtolower($parts['host'] ?? '') !== $appHost) {
                $fail('Host redirect URI harus sama dengan host aplikasi.');

                return;
            }

            if (($parts['path'] ?? '') !== '/auth/google/callback') {
                $fail('Path redirect URI harus /auth/google/callback.');
            }
        };
    }
}
