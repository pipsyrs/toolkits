<?php

namespace Tests\Feature;

use App\Actions\Admin\UpdateAppSettings;
use App\Actions\Admin\UpdateBrandAssets;
use App\Livewire\Admin\Settings\Edit;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\Permission;
use App\Models\User;
use App\Services\Settings\SettingRegistry;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const SMTP_SECRET = 'smtp-Pass-Plain-9876';

    private const ENV_GOOGLE_SECRET = 'env-google-secret-ABCD';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config(['app.url' => 'https://toolkits.test', 'services.google.client_secret' => self::ENV_GOOGLE_SECRET]);
        app(SettingsRepository::class)->forget();
        // Re-capture .env baseline after the config changes above.
        $this->app->forgetInstance(SettingsRepository::class);
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('superadmin');
    }

    private function confirmed(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    private function freshRepository(): SettingsRepository
    {
        return new SettingsRepository(new SettingRegistry);
    }

    private function update(User $admin, array $values = [], array $secrets = [], array $clear = []): array
    {
        $this->actingAs($admin)->withSession($this->confirmed());
        session($this->confirmed());

        return app(UpdateAppSettings::class)->handle($admin, $values, $secrets, $clear, '127.0.0.1');
    }

    private function snapshot(string $html): string
    {
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m, 'snapshot not found');

        return html_entity_decode($m[1], ENT_QUOTES);
    }

    public function test_seeder_creates_catalog_and_removes_unknown_permissions(): void
    {
        Permission::findOrCreate('legacy.unknown', 'web');

        $this->seed(PermissionSeeder::class);

        $this->assertTrue(Permission::where('name', 'settings.update')->exists());
        $this->assertFalse(Permission::where('name', 'legacy.unknown')->exists());
    }

    public function test_superadmin_gate_before_only_grants_catalog_abilities(): void
    {
        $admin = $this->superadmin();

        $this->assertTrue(Gate::forUser($admin)->allows('settings.update'));
        $this->assertFalse(Gate::forUser($admin)->allows('not.in.catalog'));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('settings.update'));
    }

    public function test_client_gets_403_on_page_and_livewire_replay(): void
    {
        $admin = $this->superadmin();
        $client = User::factory()->create();

        $this->actingAs($client)->withSession($this->confirmed())->get('/admin/settings')->assertForbidden();

        $html = $this->actingAs($admin)->withSession($this->confirmed())->get('/admin/settings')->assertOk()->getContent();

        $this->actingAs($client)->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => $this->snapshot($html),
                'updates' => ['values.general.site_name' => 'Hacked'],
                'calls' => [['method' => 'save', 'params' => [], 'metadata' => []]],
            ]],
        ])->assertForbidden();

        $this->assertSame(0, AppSetting::count());
    }

    public function test_page_requires_recent_password_confirmation(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->get('/admin/settings')->assertRedirect(route('password.confirm'));

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time() - 1000])
            ->get('/admin/settings')
            ->assertRedirect(route('password.confirm'));
    }

    public function test_save_without_recent_confirmation_is_rejected(): void
    {
        $admin = $this->superadmin();
        session(['auth.password_confirmed_at' => time() - 1000]);

        Livewire::actingAs($admin)->test(Edit::class)
            ->set('values.general.site_name', 'Baru')
            ->call('save')
            ->assertRedirect(route('password.confirm'));

        $this->assertSame(0, AppSetting::count());

        $this->expectException(AuthorizationException::class);
        app(UpdateAppSettings::class)->handle($admin, ['general' => ['site_name' => 'Baru']], [], [], null);
    }

    public function test_action_rejects_actor_without_permission(): void
    {
        $client = User::factory()->create();
        session($this->confirmed());

        $this->expectException(AuthorizationException::class);
        app(UpdateAppSettings::class)->handle($client, ['general' => ['site_name' => 'X']], [], [], null);
    }

    public function test_keys_outside_registry_are_ignored(): void
    {
        $admin = $this->superadmin();

        $this->update($admin, ['app' => ['key' => 'evil'], 'evil.flat' => 'x', 'general' => ['site_name' => 'OK']], ['app.cipher' => 'evil']);

        $this->assertSame(['general.site_name'], AppSetting::pluck('key')->all());
        $this->assertFalse(app(SettingRegistry::class)->has('app.key'));
    }

    public function test_values_are_encrypted_in_db_and_cache(): void
    {
        $admin = $this->superadmin();

        $this->update($admin, ['mail' => ['host' => 'smtp.example.com']], ['mail' => ['password' => self::SMTP_SECRET]]);

        $raw = DB::table('app_settings')->where('key', 'mail.password')->value('value');
        $this->assertNotSame(self::SMTP_SECRET, $raw);
        $this->assertStringNotContainsString(self::SMTP_SECRET, $raw);
        $this->assertSame(self::SMTP_SECRET, Crypt::decryptString($raw));
        $this->assertNotSame('smtp.example.com', DB::table('app_settings')->where('key', 'mail.host')->value('value'));

        $repo = app(SettingsRepository::class);
        $this->assertSame(self::SMTP_SECRET, $repo->get('mail.password'));

        $cached = Cache::store('array')->get(SettingsRepository::CACHE_KEY);
        $this->assertSame($raw, $cached['mail.password']);
        $this->assertStringNotContainsString(self::SMTP_SECRET, serialize($cached));
    }

    public function test_update_overrides_config_and_invalidates_cache(): void
    {
        $admin = $this->superadmin();
        $repo = app(SettingsRepository::class);
        $this->assertSame([], $repo->raw());

        $this->update($admin, ['general' => ['site_name' => 'Toolkits Baru'], 'mail' => ['port' => '2525']], ['xendit' => ['secret_key' => 'xnd_development_abc123']]);

        $this->assertArrayHasKey('general.site_name', Cache::store('array')->get(SettingsRepository::CACHE_KEY) ?? $repo->raw());
        $this->assertGreaterThan(0, (int) Cache::get('illuminate:queue:restart'));

        $repo->applyToConfig();
        $this->assertSame('Toolkits Baru', config('app.name'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('xnd_development_abc123', config('services.xendit.secret_key'));
        $this->assertSame('db', $repo->source('general.site_name'));
    }

    public function test_empty_value_and_clear_fall_back_to_env(): void
    {
        $admin = $this->superadmin();
        config(['app.name' => 'EnvName', 'mail.mailers.smtp.password' => null]);

        $this->update($admin, ['general' => ['site_name' => 'DbName']], ['mail' => ['password' => self::SMTP_SECRET]]);
        $this->assertSame(2, AppSetting::count());

        // Empty secret input = unchanged.
        $this->update($admin, [], ['mail' => ['password' => '']]);
        $this->assertSame(self::SMTP_SECRET, $this->freshRepository()->get('mail.password'));

        $changes = $this->update($admin, ['general' => ['site_name' => '']], [], ['mail' => ['password' => true]]);
        $this->assertSame(['general.site_name' => ['old' => 'DbName', 'new' => null], 'mail.password' => 'cleared'], $changes);
        $this->assertSame(0, AppSetting::count());

        $repo = $this->freshRepository();
        $repo->applyToConfig();
        $this->assertSame('EnvName', config('app.name'));
        $this->assertSame('env', $repo->source('general.site_name'));
        $this->assertSame('empty', $repo->source('mail.password'));
        $this->assertSame('env', $repo->source('google.client_secret'));
    }

    public function test_config_cache_command_does_not_apply_db_values(): void
    {
        $admin = $this->superadmin();
        config(['mail.mailers.smtp.password' => null]);
        $this->update($admin, [], ['mail' => ['password' => self::SMTP_SECRET]]);

        $argv = $_SERVER['argv'] ?? null;

        try {
            foreach ([['config:cache'], ['optimize'], ['--env=production', 'config:cache']] as $args) {
                $_SERVER['argv'] = ['artisan', ...$args];

                $this->freshRepository()->applyToConfig();
                $this->assertNull(config('mail.mailers.smtp.password'));
                $this->assertStringNotContainsString(self::SMTP_SECRET, var_export(config()->all(), true));
            }
        } finally {
            $_SERVER['argv'] = $argv;
        }

        $this->freshRepository()->applyToConfig();
        $this->assertSame(self::SMTP_SECRET, config('mail.mailers.smtp.password'));
    }

    public function test_validation_rules_are_enforced(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin);
        session($this->confirmed());

        Livewire::test(Edit::class)
            ->set('values.google.redirect', 'https://evil.example/auth/google/callback')
            ->set('values.mail.host', 'https://smtp.example.com')
            ->set('values.mail.port', '70000')
            ->set('secrets.xendit.secret_key', 'sk_live_wrong')
            ->set('secrets.xendit.callback_token', 'short')
            ->call('save')
            ->assertHasErrors([
                'values.google.redirect',
                'values.mail.host',
                'values.mail.port',
                'secrets.xendit.secret_key',
                'secrets.xendit.callback_token',
            ])
            ->assertSet('secrets.xendit.secret_key', '');

        $this->assertSame(0, AppSetting::count());

        Livewire::test(Edit::class)
            ->set('values.google.redirect', 'https://toolkits.test/auth/google/callback')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('https://toolkits.test/auth/google/callback', $this->freshRepository()->get('google.redirect'));
    }

    public function test_secrets_never_leak_into_html_or_snapshot(): void
    {
        $admin = $this->superadmin();
        $this->update($admin, [], ['mail' => ['password' => self::SMTP_SECRET], 'xendit' => ['callback_token' => 'cb-token-plaintext-123456']]);

        $html = $this->actingAs($admin)->withSession($this->confirmed())->get('/admin/settings')->assertOk()->getContent();
        $snapshot = $this->snapshot($html);

        foreach ([self::SMTP_SECRET, 'cb-token-plaintext-123456', self::ENV_GOOGLE_SECRET] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
            $this->assertStringNotContainsString($secret, $snapshot);
        }

        // Masked shows only the last 4 chars.
        $this->assertStringContainsString(SettingsRepository::MASK.'9876', $html);
        $this->assertStringContainsString(SettingsRepository::MASK.'ABCD', $html);

        // After save the typed secret is cleared from component state.
        $this->actingAs($admin);
        session($this->confirmed());

        $component = Livewire::test(Edit::class)
            ->set('secrets.mail.password', 'new-secret-value-XYZ1')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('secrets.mail.password', '');

        $this->assertStringNotContainsString('new-secret-value-XYZ1', $component->html());
        $this->assertStringNotContainsString('new-secret-value-XYZ1', json_encode($component->snapshot));
        $this->assertSame('new-secret-value-XYZ1', $this->freshRepository()->get('mail.password'));
    }

    public function test_audit_log_records_changes_without_secret_values(): void
    {
        $admin = $this->superadmin();

        $this->update($admin, ['general' => ['site_name' => 'Satu']], ['mail' => ['password' => self::SMTP_SECRET]]);
        $this->update($admin, ['general' => ['site_name' => 'Satu']], ['mail' => ['password' => self::SMTP_SECRET]]);

        $logs = AdminAuditLog::where('action', 'settings.updated')->get();
        $this->assertCount(1, $logs, 'unchanged values must not create another log');

        $log = $logs->first();
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame((new AppSetting)->getMorphClass(), $log->subject_type);
        $this->assertSame(['old' => null, 'new' => 'Satu'], $log->changes['general.site_name']);
        $this->assertSame('changed', $log->changes['mail.password']);
        $this->assertStringNotContainsString(self::SMTP_SECRET, json_encode($log->getAttributes()));
    }

    public function test_send_test_mail_is_throttled(): void
    {
        $admin = $this->superadmin();
        $this->actingAs($admin);

        $component = Livewire::test(Edit::class);

        for ($i = 0; $i < 3; $i++) {
            $component->call('sendTestMail')->assertSee('Email uji dikirim ke '.$admin->email);
        }

        $component->call('sendTestMail')->assertSee('Terlalu banyak percobaan');
    }

    public function test_logo_and_favicon_are_reencoded_stored_and_served(): void
    {
        Storage::fake('public');
        $admin = $this->superadmin();
        $this->actingAs($admin);
        session($this->confirmed());

        Livewire::test(Edit::class)
            ->set('uploads.branding.logo', UploadedFile::fake()->image('logo.png', 1200, 300))
            ->set('uploads.branding.favicon', UploadedFile::fake()->image('icon.jpg', 400, 400))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('uploads', []);

        $repo = $this->freshRepository();
        $logo = $repo->get('branding.logo');
        $favicon = $repo->get('branding.favicon');

        $this->assertMatchesRegularExpression('#^branding/[0-9a-f-]{36}\.webp$#', $logo);
        $this->assertMatchesRegularExpression('#^branding/[0-9a-f-]{36}\.png$#', $favicon);
        $this->assertSame([512, 128], array_slice(getimagesize(Storage::disk('public')->path($logo)), 0, 2));
        $this->assertSame([180, 180], array_slice(getimagesize(Storage::disk('public')->path($favicon)), 0, 2));
        $this->assertSame(['old' => null, 'new' => $logo], AdminAuditLog::where('action', 'settings.updated')->latest('id')->first()->changes['branding.logo']);

        $repo->applyToConfig();
        $this->get(route('home'))->assertSee(Storage::disk('public')->url($logo))->assertSee(Storage::disk('public')->url($favicon));
    }

    public function test_invalid_upload_blocks_the_whole_save(): void
    {
        Storage::fake('public');
        $admin = $this->superadmin();
        $this->actingAs($admin);
        session($this->confirmed());

        Livewire::test(Edit::class)
            ->set('values.general.site_name', 'Tidak tersimpan')
            ->set('uploads.branding.logo', UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
            ->call('save')
            ->assertHasErrors(['branding.logo']);

        $this->assertSame(0, AppSetting::count());
        $this->assertSame([], Storage::disk('public')->allFiles());

        // A disguised file passes the MIME check in tests but fails re-encoding: still a field error, nothing stored.
        try {
            app(UpdateBrandAssets::class)->handle($admin, ['branding.favicon' => UploadedFile::fake()->createWithContent('icon.png', 'bukan gambar')], []);
            $this->fail('disguised file must be rejected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('branding.favicon', $e->errors());
        }

        $this->assertSame(0, AppSetting::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_replacing_and_clearing_brand_images_removes_old_files(): void
    {
        Storage::fake('public');
        $admin = $this->superadmin();
        $this->actingAs($admin);
        session($this->confirmed());
        $action = app(UpdateBrandAssets::class);

        $action->handle($admin, ['branding.logo' => UploadedFile::fake()->image('a.png', 50, 20)], []);
        $first = $this->freshRepository()->get('branding.logo');

        $action->handle($admin, ['branding.logo' => UploadedFile::fake()->image('b.png', 50, 20)], []);
        $second = $this->freshRepository()->get('branding.logo');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        Livewire::test(Edit::class)
            ->set('clear.branding.logo', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($this->freshRepository()->get('branding.logo'));
        Storage::disk('public')->assertMissing($second);
        $this->assertSame([], $action->handle($admin, [], ['branding.logo']), 'removing nothing is a no-op');
    }

    public function test_brand_action_rejects_actor_without_permission_or_raw_paths(): void
    {
        Storage::fake('public');
        session($this->confirmed());

        try {
            app(UpdateBrandAssets::class)->handle(User::factory()->create(), ['branding.logo' => UploadedFile::fake()->image('a.png')], []);
            $this->fail('client must not update branding');
        } catch (AuthorizationException) {
        }

        // A path string is never accepted as a value, by either action.
        $admin = $this->superadmin();
        $this->update($admin, ['branding' => ['logo' => '../../.env']]);
        $this->assertSame([], app(UpdateBrandAssets::class)->handle($admin, ['branding.logo' => '../../.env'], []));
        $this->assertSame(0, AppSetting::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
