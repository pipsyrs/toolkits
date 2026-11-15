<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\Edit;
use App\Models\AdminAuditLog;
use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingRegistry;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class AppSettingsQaTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'qa-google-secret-Z9Y8';

    private const ENV_SMTP_PASSWORD = 'env-smtp-password-QRST';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config([
            'app.url' => 'https://toolkits.test',
            'mail.mailers.smtp.host' => 'env-smtp.example.com',
            'mail.mailers.smtp.password' => self::ENV_SMTP_PASSWORD,
            'services.google.client_id' => 'env-client-id',
            'services.google.client_secret' => null,
        ]);
        app(SettingsRepository::class)->forget();
        $this->app->forgetInstance(SettingsRepository::class);
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('superadmin');
    }

    private function asConfirmed(User $user): void
    {
        $this->actingAs($user);
        session(['auth.password_confirmed_at' => time()]);
    }

    private function freshRepository(): SettingsRepository
    {
        return new SettingsRepository(new SettingRegistry);
    }

    private function snapshot(string $html): string
    {
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m, 'snapshot not found');

        return html_entity_decode($m[1], ENT_QUOTES);
    }

    private function saveViaLivewire(User $admin, array $sets): Testable
    {
        $this->asConfirmed($admin);
        $component = Livewire::test(Edit::class);

        foreach ($sets as $path => $value) {
            $component->set($path, $value);
        }

        return $component->call('save');
    }

    public function test_page_renders_all_groups_with_source_badges(): void
    {
        $admin = $this->superadmin();
        $this->saveViaLivewire($admin, ['values.general.site_name' => 'Dari DB'])->assertHasNoErrors();

        $html = $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee(['Umum', 'Email / SMTP', 'Login Google', 'Payment gateway'])
            ->assertSeeInOrder(['Nama situs', 'Database', 'Email dukungan'])
            ->assertSeeInOrder(['SMTP host', '.env', 'SMTP port'])
            ->assertSeeInOrder(['Client secret', 'Kosong', 'Redirect URI'])
            ->getContent();

        $this->assertStringNotContainsString(self::ENV_SMTP_PASSWORD, $html);
        $this->assertStringNotContainsString(self::ENV_SMTP_PASSWORD, $this->snapshot($html));
        $this->assertStringContainsString(SettingsRepository::MASK.'QRST', $html);
    }

    public function test_livewire_save_overrides_config_and_invalidates_cache(): void
    {
        $admin = $this->superadmin();
        $repo = app(SettingsRepository::class);
        $this->assertSame([], $repo->raw());
        $this->assertSame([], Cache::store('array')->get(SettingsRepository::CACHE_KEY));

        $this->saveViaLivewire($admin, [
            'values.google.client_id' => 'db-client-id.apps.googleusercontent.com',
            'values.mail.host' => 'smtp.db.example.com',
            'secrets.google.client_secret' => self::SECRET,
        ])->assertHasNoErrors()->assertSee('Pengaturan disimpan.')->assertSet('secrets.google.client_secret', '');

        // Stale [] cache was dropped; re-warmed with the new rows (ciphertext only).
        $cached = Cache::store('array')->get(SettingsRepository::CACHE_KEY);
        $this->assertEqualsCanonicalizing(['google.client_id', 'google.client_secret', 'mail.host'], array_keys($cached));
        $this->assertStringNotContainsString(self::SECRET, serialize($cached));

        $next = $this->freshRepository();
        $next->applyToConfig();
        $this->assertSame('db-client-id.apps.googleusercontent.com', config('services.google.client_id'));
        $this->assertSame('smtp.db.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(self::SECRET, config('services.google.client_secret'));
        $this->assertSame('env-smtp.example.com', $next->envValue('mail.host'));
    }

    public function test_secret_save_without_recent_confirmation_is_rejected(): void
    {
        $admin = $this->superadmin();
        $this->actingAs($admin);
        session(['auth.password_confirmed_at' => time() - 901]);

        Livewire::test(Edit::class)
            ->set('secrets.google.client_secret', self::SECRET)
            ->call('save')
            ->assertRedirect(route('password.confirm'));

        $this->assertSame(0, AppSetting::count());
        $this->assertSame(0, AdminAuditLog::where('action', 'settings.updated')->count());
    }

    public function test_type_validation_per_key(): void
    {
        $admin = $this->superadmin();

        $this->saveViaLivewire($admin, [
            'values.general.site_name' => str_repeat('a', 61),
            'values.general.support_email' => 'bukan-email',
            'values.mail.mailer' => 'sendmail',
            'values.mail.scheme' => 'http',
            'values.mail.from_address' => 'x@',
            'values.xendit.expiry_minutes' => 'abc',
        ])->assertHasErrors([
            'values.general.site_name',
            'values.general.support_email',
            'values.mail.mailer',
            'values.mail.scheme',
            'values.mail.from_address',
            'values.xendit.expiry_minutes',
        ]);

        $this->assertSame(0, AppSetting::count());
    }

    public function test_int_boundary_values(): void
    {
        $admin = $this->superadmin();

        $this->saveViaLivewire($admin, ['values.xendit.expiry_minutes' => '10081', 'values.mail.port' => '0'])
            ->assertHasErrors(['values.xendit.expiry_minutes', 'values.mail.port']);

        $this->saveViaLivewire($admin, ['values.xendit.expiry_minutes' => '10080', 'values.mail.port' => '65535'])
            ->assertHasNoErrors();

        $this->assertSame(10080, $this->freshRepository()->get('xendit.expiry_minutes'));
        $this->assertSame(65535, $this->freshRepository()->get('mail.port'));
    }

    public function test_tampered_livewire_properties_outside_registry_are_ignored(): void
    {
        $admin = $this->superadmin();
        $appKey = config('app.key');

        $this->saveViaLivewire($admin, [
            'values.app' => ['key' => 'evil', 'debug' => true],
            'values.mail.password' => 'plain-via-values',
            'secrets.app' => ['key' => 'evil-secret'],
            'clear.general' => ['site_name' => true],
            'values.general.site_name' => 'OK',
        ])->assertHasNoErrors();

        $this->assertSame(['general.site_name'], AppSetting::pluck('key')->all());

        $this->freshRepository()->applyToConfig();
        $this->assertSame($appKey, config('app.key'));
        $this->assertSame(self::ENV_SMTP_PASSWORD, config('mail.mailers.smtp.password'));
    }

    public function test_tampered_array_value_does_not_crash_or_persist(): void
    {
        $admin = $this->superadmin();

        $this->saveViaLivewire($admin, [
            'values.general.site_name' => ['nested' => 'x'],
            'secrets.google.client_secret' => ['nested' => 'x'],
        ])->assertStatus(200);

        $this->assertSame(0, AppSetting::count());
    }

    public function test_clear_checkbox_falls_back_to_env(): void
    {
        $admin = $this->superadmin();
        $this->saveViaLivewire($admin, ['secrets.mail.password' => 'db-smtp-password-1234'])->assertHasNoErrors();
        $this->assertSame('db', $this->freshRepository()->source('mail.password'));

        $this->saveViaLivewire($admin, ['clear.mail.password' => true])
            ->assertHasNoErrors()
            ->assertSet('clear.mail.password', false);

        $this->assertSame(0, AppSetting::count());
        $repo = $this->freshRepository();
        $repo->applyToConfig();
        $this->assertSame('env', $repo->source('mail.password'));
        $this->assertSame(self::ENV_SMTP_PASSWORD, config('mail.mailers.smtp.password'));
    }

    public function test_settings_menu_visible_only_with_permission(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee(route('admin.settings.edit'), false)
            ->assertSee('Pengaturan');

        // F2: menu is per permission; auditor sees audit log, not settings.
        $client = User::factory()->create();
        $client->assignRole(Role::findOrCreate('auditor', 'web')->givePermissionTo(['admin.access', 'audit.view']));
        $this->actingAs($client);
        $html = Blade::render('<x-layouts::admin>isi</x-layouts::admin>');

        $this->assertStringContainsString('Audit log', $html);
        $this->assertStringNotContainsString(route('admin.settings.edit'), $html);
        $this->assertStringNotContainsString('Pengaturan', $html);
    }

    public function test_client_cannot_mount_component_directly(): void
    {
        $client = User::factory()->create();
        $this->asConfirmed($client);

        Livewire::test(Edit::class)->assertForbidden();
    }

    public function test_livewire_audit_log_has_no_secret_values(): void
    {
        $admin = $this->superadmin();

        $this->saveViaLivewire($admin, [
            'values.mail.host' => 'smtp.audit.example.com',
            'secrets.google.client_secret' => self::SECRET,
        ])->assertHasNoErrors();
        $this->saveViaLivewire($admin, ['clear.google.client_secret' => true])->assertHasNoErrors();

        $logs = AdminAuditLog::where('action', 'settings.updated')->orderBy('created_at')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['old' => null, 'new' => 'smtp.audit.example.com'], $logs[0]->changes['mail.host']);
        $this->assertSame('changed', $logs[0]->changes['google.client_secret']);
        $this->assertSame('cleared', $logs[1]->changes['google.client_secret']);
        $this->assertStringNotContainsString(self::SECRET, json_encode(DB::table('admin_audit_logs')->get()));
    }

    private function rotateAppKey(): void
    {
        $encrypter = new Encrypter(random_bytes(32), 'aes-256-cbc');
        $this->app->instance('encrypter', $encrypter);
        Crypt::swap($encrypter);
        app(SettingsRepository::class)->forget();
        $this->app->forgetInstance(SettingsRepository::class);
    }

    public function test_changed_app_key_sets_decrypt_errors_without_crashing(): void
    {
        $admin = $this->superadmin();
        $this->saveViaLivewire($admin, [
            'values.mail.host' => 'smtp.old-key.example.com',
            'secrets.mail.password' => 'old-key-password-5678',
        ])->assertHasNoErrors();

        $this->rotateAppKey();
        Exceptions::fake();

        $repo = $this->freshRepository();
        $repo->applyToConfig();
        $this->assertTrue($repo->hasDecryptErrors());
        $this->assertNull($repo->get('mail.password'));
        $this->assertSame('env-smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(self::ENV_SMTP_PASSWORD, config('mail.mailers.smtp.password'));
        Exceptions::assertReported(DecryptException::class);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Sebagian pengaturan tidak bisa dibaca');
    }

    public function test_resaving_value_after_app_key_change_recovers(): void
    {
        $admin = $this->superadmin();
        $this->saveViaLivewire($admin, ['secrets.mail.password' => 'old-key-password-5678'])->assertHasNoErrors();

        $this->rotateAppKey();

        // UI says "Isi ulang nilai yang terdampak": re-filling must work.
        $this->saveViaLivewire($admin, ['secrets.mail.password' => 'new-key-password-9012'])->assertHasNoErrors();
        $this->assertSame('new-key-password-9012', $this->freshRepository()->get('mail.password'));
    }

    public function test_clearing_value_after_app_key_change_recovers(): void
    {
        $admin = $this->superadmin();
        $this->saveViaLivewire($admin, ['secrets.mail.password' => 'old-key-password-5678'])->assertHasNoErrors();

        $this->rotateAppKey();

        $this->saveViaLivewire($admin, ['clear.mail.password' => true])->assertHasNoErrors();
        $this->assertSame(0, AppSetting::count());
    }
}
