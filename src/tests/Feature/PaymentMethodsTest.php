<?php

namespace Tests\Feature;

use App\Livewire\Admin\PaymentMethods\Index;
use App\Livewire\Client\Billing;
use App\Models\AdminAuditLog;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\Billing\PaymentMethodCatalog;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('superadmin');
    }

    private function method(string $channel): PaymentMethod
    {
        return PaymentMethod::query()->where('channel', $channel)->sole();
    }

    public function test_page_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/payment-methods')->assertForbidden();

        $finance = User::factory()->create()->assignRole(Role::create(['name' => 'finance', 'guard_name' => 'web'])->givePermissionTo(['admin.access', 'payments.view_any']));
        $this->actingAs($finance)->get('/admin/payment-methods')->assertForbidden();
        Livewire::actingAs($finance)->test(Index::class)->assertForbidden();

        $manager = User::factory()->create()->assignRole(Role::create(['name' => 'billing', 'guard_name' => 'web'])->givePermissionTo(['admin.access', 'payment_methods.view_any']));
        $this->actingAs($manager)->get('/admin/payment-methods')->assertOk()->assertSee('BCA Virtual Account')->assertSee('OVO');
        $this->actingAs($this->admin())->get('/admin/payment-methods')->assertOk();
    }

    public function test_defaults_offer_qris_and_va_with_ewallets_off(): void
    {
        $this->assertSame(['QRIS', 'BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA', 'BSI'], array_keys(app(PaymentMethodCatalog::class)->active()));
        $this->assertFalse($this->method('DANA')->is_active);
    }

    public function test_toggle_updates_checkout_catalog_and_is_audited(): void
    {
        $catalog = app(PaymentMethodCatalog::class);
        $this->assertArrayNotHasKey('DANA', $catalog->active());

        Livewire::actingAs($this->admin())->test(Index::class)->call('toggle', $this->method('DANA')->id)->assertHasNoErrors();

        $this->assertTrue($this->method('DANA')->is_active);
        $this->assertArrayHasKey('DANA', $catalog->active());
        $this->assertSame(1, AdminAuditLog::where('action', 'payment_method.toggle')->count());

        Livewire::actingAs($this->admin())->test(Index::class)->call('toggle', $this->method('DANA')->id);
        $this->assertArrayNotHasKey('DANA', $catalog->active());
    }

    public function test_methods_have_no_own_name_and_checkout_shows_only_badge(): void
    {
        $this->seed(PlanSeeder::class);
        config(['services.xendit.secret_key' => 'xnd_development_secret']);

        $this->assertFalse(Schema::hasColumn('payment_methods', 'name'));
        $this->actingAs($this->admin())->get('/admin/payment-methods')->assertOk()
            ->assertSee('BCA Virtual Account')->assertDontSee('Nama di checkout');

        // Badge carries the short channel name; the full name is only for screen readers.
        Livewire::actingAs(User::factory()->create())->test(Billing::class)->call('startOrder', 'pro', 'monthly')
            ->assertSeeHtml('<span class="sr-only">BCA Virtual Account</span>')
            ->assertDontSeeHtml('line-clamp-2');
    }

    public function test_list_is_flat_and_move_reorders_whole_list(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Index::class)
            ->assertSeeInOrder(['QRIS', 'BCA Virtual Account', 'OVO']);

        $component->call('move', $this->method('BNI')->id, true);
        $this->assertSame(['QRIS', 'BNI', 'BCA'], array_slice(array_keys(app(PaymentMethodCatalog::class)->active()), 0, 3));

        // Moving above QRIS changes the list order; checkout still groups by channel kind.
        $component->call('move', $this->method('BNI')->id, true);
        $this->assertSame('BNI', PaymentMethod::query()->orderBy('sort')->first()->channel);

        $component->call('move', $this->method('BNI')->id, true)->assertHasNoErrors();
        $this->assertSame('BNI', PaymentMethod::query()->orderBy('sort')->first()->channel);
    }

    public function test_delete_and_add_back_from_catalog(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Index::class);

        $component->call('delete', $this->method('BSI')->id)->assertHasNoErrors();
        $this->assertNull(PaymentMethod::query()->where('channel', 'BSI')->first());
        $this->assertArrayNotHasKey('BSI', app(PaymentMethodCatalog::class)->active());

        $component->call('openCreate')->set('channel', 'PAYPAL')->call('save')->assertHasErrors('channel');
        $component->set('channel', 'BCA')->call('save')->assertHasErrors('channel');

        $component->set('channel', 'BSI')->call('save')->assertHasNoErrors()->assertSet('channel', '')->assertSet('formOpen', false);
        $added = $this->method('BSI');
        $this->assertFalse($added->is_active);
        $this->assertSame(PaymentMethod::max('sort'), $added->sort);
        $this->assertSame(1, AdminAuditLog::where('action', 'payment_method.create')->count());
        $this->assertSame(1, AdminAuditLog::where('action', 'payment_method.delete')->count());
    }

    public function test_unknown_id_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('toggle', '00000000-0000-7000-8000-000000000000')
            ->assertHasErrors('list');

        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('openEdit', '00000000-0000-7000-8000-000000000000')
            ->assertHasErrors('list')
            ->assertSet('formOpen', false);
    }

    public function test_logo_is_reencoded_and_replaces_name_in_checkout(): void
    {
        Storage::fake(PaymentMethod::LOGO_DISK);
        $this->seed(PlanSeeder::class);
        config(['services.xendit.secret_key' => 'xnd_development_secret']);
        $method = $this->method('BCA');

        Livewire::actingAs($this->admin())->test(Index::class)
            ->call('openEdit', $method->id)
            ->set('logo', UploadedFile::fake()->image('bca.png', 600, 200))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('logo', null);

        $path = $method->fresh()->logo_path;
        $this->assertMatchesRegularExpression('#^payment-logos/[0-9a-f-]{36}\.webp$#', (string) $path);
        Storage::disk(PaymentMethod::LOGO_DISK)->assertExists($path);
        $size = getimagesizefromstring(Storage::disk(PaymentMethod::LOGO_DISK)->get($path));
        $this->assertSame([256, 85, IMAGETYPE_WEBP], [$size[0], $size[1], $size[2]]);

        // Checkout tile shows the logo (alt = name) instead of the name text.
        $checkout = Livewire::actingAs(User::factory()->create())->test(Billing::class)->call('startOrder', 'pro', 'monthly');
        $checkout->assertSeeHtml('alt="BCA Virtual Account"')->assertSeeHtml(PaymentMethod::logoUrl($path))
            ->assertDontSeeHtml('<span class="sr-only">BCA Virtual Account</span>')
            ->assertSeeHtml('<span class="sr-only">BNI Virtual Account</span>');

        // Replacing deletes the old file; removing goes back to the name.
        $component = Livewire::actingAs($this->admin())->test(Index::class)->call('openEdit', $method->id)
            ->set('logo', UploadedFile::fake()->image('bca.jpg', 100, 100))->call('save')->assertHasNoErrors();
        Storage::disk(PaymentMethod::LOGO_DISK)->assertMissing($path);
        $replaced = $method->fresh()->logo_path;

        $component->call('openEdit', $method->id)->call('removeLogo')->assertHasNoErrors();
        $this->assertNull($method->fresh()->logo_path);
        Storage::disk(PaymentMethod::LOGO_DISK)->assertMissing($replaced);
        $this->assertSame(3, AdminAuditLog::where('action', 'payment_method.update')->count());
    }

    public function test_logo_rejects_svg_disguised_and_oversized_files(): void
    {
        Storage::fake(PaymentMethod::LOGO_DISK);
        $component = Livewire::actingAs($this->admin())->test(Index::class)->call('openEdit', $this->method('BCA')->id);

        $files = [
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('logo.png', '<?php system($_GET["c"]); ?>'),
            UploadedFile::fake()->image('big.png', 2000, 2000)->size(600),
        ];

        foreach ($files as $file) {
            $component->set('logo', $file)->call('save')->assertHasErrors('logo')->assertSet('formOpen', true);
        }

        $this->assertNull($this->method('BCA')->logo_path);
        $this->assertSame([], Storage::disk(PaymentMethod::LOGO_DISK)->allFiles());
    }

    public function test_deleting_method_removes_its_logo(): void
    {
        Storage::fake(PaymentMethod::LOGO_DISK);
        $method = $this->method('BRI');
        $component = Livewire::actingAs($this->admin())->test(Index::class)
            ->call('openEdit', $method->id)->set('logo', UploadedFile::fake()->image('bri.webp', 120, 40))->call('save')->assertHasNoErrors();
        $path = $method->fresh()->logo_path;

        $component->call('delete', $method->id)->assertHasNoErrors();

        Storage::disk(PaymentMethod::LOGO_DISK)->assertMissing($path);
    }

    public function test_ui_does_not_mention_gateway_brand(): void
    {
        $this->seed(PlanSeeder::class);
        $admin = $this->admin();

        $this->get('/pricing')->assertOk()->assertDontSee('Xendit');
        foreach (['/admin/payment-methods', '/admin/payment-channels', '/admin/settings', '/dashboard/billing'] as $url) {
            $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->get($url)->assertOk()->assertDontSee('Xendit');
        }

        Livewire::actingAs($admin)->test(Index::class)->call('openCreate')->set('channel', 'CARD')->call('save')->assertHasErrors('channel');
        PaymentMethod::query()->where('channel', 'CARD')->delete();
        Livewire::actingAs($admin)->test(Index::class)->call('openCreate')->set('channel', 'CARD')->call('save')->assertHasNoErrors()->assertDontSee('Xendit');
    }
}
