<?php

namespace Tests\Feature;

use App\Livewire\Admin\Plans\Limits;
use App\Livewire\Tools\QrCode;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_generates_valid_png(): void
    {
        $uri = Livewire::test(QrCode::class)
            ->set('text', 'https://example.com/hello')
            ->set('size', 300)
            ->call('generate')
            ->assertHasNoErrors()
            ->get('dataUri');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $png = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);

        $info = getimagesizefromstring($png);
        $this->assertSame('image/png', $info['mime']);
        $this->assertSame($info[0], $info[1]);
        $this->assertGreaterThanOrEqual(200, $info[0]);
        $this->assertLessThanOrEqual(400, $info[0]);
    }

    public function test_pro_generates_valid_svg_without_user_text(): void
    {
        $component = Livewire::actingAs(User::factory()->pro()->create())->test(QrCode::class)
            ->set('text', '<script>alert(1)</script>')
            ->set('format', 'svg')
            ->call('generate')
            ->assertHasNoErrors();

        $svg = $component->get('svg');
        $xml = simplexml_load_string($svg);
        $this->assertNotFalse($xml, 'SVG must be well-formed XML');
        $this->assertSame('svg', $xml->getName());
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertSame('data:image/svg+xml;base64,'.base64_encode($svg), $component->get('dataUri'));
    }

    public function test_svg_forbidden_for_free_and_guest(): void
    {
        Livewire::test(QrCode::class)
            ->set('text', 'abc')->set('format', 'svg')->call('generate')
            ->assertHasErrors('format')->assertSet('svg', null);

        Livewire::actingAs(User::factory()->create())->test(QrCode::class)
            ->set('text', 'abc')->set('format', 'svg')->call('generate')
            ->assertHasErrors('format');
    }

    public function test_rejects_invalid_input(): void
    {
        $max = (int) config('toolkits.qr.max_text');

        Livewire::test(QrCode::class)->set('text', str_repeat('a', $max))->call('generate')->assertHasNoErrors();
        Livewire::test(QrCode::class)->set('text', str_repeat('a', $max + 1))->call('generate')->assertHasErrors('text')->assertSet('dataUri', null);
        Livewire::test(QrCode::class)->set('text', '')->call('generate')->assertHasErrors('text');
        Livewire::test(QrCode::class)->set('text', 'a')->set('size', 199)->call('generate')->assertHasErrors('size');
        Livewire::test(QrCode::class)->set('text', 'a')->set('size', 1001)->call('generate')->assertHasErrors('size');
        Livewire::test(QrCode::class)->set('text', 'a')->set('ecc', 'X')->call('generate')->assertHasErrors('ecc');
        Livewire::test(QrCode::class)->set('text', 'a')->set('format', 'gif')->call('generate')->assertHasErrors('format');
    }

    public function test_generate_is_rate_limited_for_stable_guest(): void
    {
        $component = Livewire::withCookies(['tk_gid' => (string) Str::uuid()])->test(QrCode::class)->set('text', 'a');

        for ($i = 0; $i < 30; $i++) {
            $component->call('generate')->assertHasNoErrors();
        }

        $component->call('generate')->assertHasErrors('text');
    }

    // Dropping the tk_gid cookie must not reset the per-IP budget.
    public function test_generate_rate_limit_not_bypassed_without_guest_cookie(): void
    {
        for ($i = 0; $i < 30; $i++) {
            Livewire::test(QrCode::class)->set('text', 'a')->call('generate');
        }

        Livewire::test(QrCode::class)->set('text', 'a')->call('generate')->assertHasErrors('text');
    }

    public function test_live_preview_follows_input_and_resets_download(): void
    {
        $component = Livewire::test(QrCode::class)
            ->set('text', 'https://example.com')
            ->assertSet('dataUri', null)
            ->assertNotDispatched('operation-done');

        $this->assertStringStartsWith('data:image/png;base64,', $component->get('previewUri'));
        $this->assertFalse($this->scannable($component->get('previewUri')), 'Live preview must not be a usable QR code');

        $component->call('generate')->assertDispatched('operation-done');
        $this->assertTrue($this->scannable($component->get('dataUri')));

        // Changed settings: the old download no longer matches what is shown.
        $component->set('ecc', 'H')->assertSet('dataUri', null);
        $this->assertNotNull($component->get('previewUri'));

        $component->set('text', '')->assertSet('previewUri', null)->assertHasNoErrors();
    }

    private function scannable(string $dataUri): bool
    {
        try {
            return (new \chillerlan\QRCode\QRCode)->readFromBlob(base64_decode(substr($dataUri, strlen('data:image/png;base64,'))))->data === 'https://example.com';
        } catch (\Throwable) {
            return false;
        }
    }

    public function test_prefill_from_query_is_truncated(): void
    {
        $max = (int) config('toolkits.qr.max_text');

        Livewire::withQueryParams(['text' => str_repeat('b', $max + 50)])
            ->test(QrCode::class)
            ->assertSet('text', str_repeat('b', $max));
    }

    public function test_page_renders_prefilled_text_escaped(): void
    {
        $this->get('/tools/qr-code?text='.urlencode('"><script>x</script>'))
            ->assertOk()
            ->assertDontSee('"><script>x</script>', false);
    }

    public function test_free_template_and_frame_work_for_guest(): void
    {
        $uri = Livewire::test(QrCode::class)
            ->set('text', 'https://example.com')
            ->set('template', 'navy')
            ->assertSet('colorDark', '#1b2a4a')
            ->set('frame', 'simple')
            ->set('frameTitle', 'Scan menu')
            ->set('frameFooter', 'contoh.com')
            ->call('generate')
            ->assertHasNoErrors()
            ->get('dataUri');

        $this->assertTrue($this->scannable($uri));
        $info = getimagesizefromstring(base64_decode(substr($uri, strlen('data:image/png;base64,'))));
        $this->assertGreaterThan($info[0], $info[1], 'Title and footer bands make the image taller than wide');
    }

    public function test_pro_styles_are_locked_for_guest_and_free(): void
    {
        foreach ([null, User::factory()->create()] as $user) {
            $component = ($user ? Livewire::actingAs($user)->test(QrCode::class) : Livewire::test(QrCode::class))->set('text', 'abc');

            $component->set('template', 'dots')->assertSet('previewUri', null)
                ->call('generate')->assertHasErrors('template')->assertSet('dataUri', null);

            $component->set('template', 'classic')->set('frame', 'card')
                ->call('generate')->assertHasErrors('frame');

            $component->set('frame', 'none')->set('colorDark', '#123456')
                ->call('generate')->assertHasErrors('colors');
        }
    }

    public function test_pro_uses_premium_template_frame_and_custom_colors(): void
    {
        $uri = Livewire::actingAs(User::factory()->pro()->create())->test(QrCode::class)
            ->set('text', 'https://example.com')
            ->set('template', 'duotone')
            ->set('frame', 'banner')
            ->set('frameTitle', 'Scan')
            ->set('colorEye', '#0b5e3a')
            ->set('colorFrame', '#ffc629')
            ->call('generate')
            ->assertHasNoErrors()
            ->get('dataUri');

        $this->assertTrue($this->scannable($uri));
    }

    public function test_admin_can_open_a_feature_to_free_in_the_plan_matrix(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create()->assignRole('superadmin');
        Livewire::actingAs($admin)->test(Limits::class)
            ->set('values.free.tool_qr_code_premium_frame', true)
            ->call('save')
            ->assertHasNoErrors();
        auth()->logout();

        Livewire::test(QrCode::class)
            ->set('text', 'abc')->set('frame', 'card')
            ->call('generate')->assertHasNoErrors();

        // Other features stay Pro.
        Livewire::test(QrCode::class)
            ->set('text', 'abc')->set('template', 'soft')
            ->call('generate')->assertHasErrors('template');
    }

    public function test_svg_frame_text_is_escaped(): void
    {
        $svg = Livewire::actingAs(User::factory()->pro()->create())->test(QrCode::class)
            ->set('text', 'abc')
            ->set('format', 'svg')
            ->set('frame', 'card')
            ->set('frameTitle', '<script>alert(1)</script>')
            ->set('frameFooter', '"/><img src=x onerror=1>')
            ->call('generate')
            ->assertHasNoErrors()
            ->get('svg');

        $this->assertNotFalse(simplexml_load_string($svg), 'SVG must be well-formed XML');
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringNotContainsString('<img', $svg);
        $this->assertStringContainsString('&lt;script&gt;', $svg);
    }

    public function test_rejects_invalid_style_input(): void
    {
        $pro = User::factory()->pro()->create();
        $base = fn () => Livewire::actingAs($pro)->test(QrCode::class)->set('text', 'abc')->set('frame', 'simple');

        $base()->set('frameTitle', str_repeat('a', 41))->call('generate')->assertHasErrors('frameTitle');
        $base()->set('frameFooter', "a\u{0007}b")->call('generate')->assertHasErrors('frameFooter');
        $base()->set('colorBg', 'red')->call('generate')->assertHasErrors('colorBg');
        $base()->set('template', 'gold')->call('generate')->assertHasErrors('template');
        $base()->set('frame', 'neon')->call('generate')->assertHasErrors('frame');
        // Light modules on a dark background do not scan reliably.
        $base()->set('colorDark', '#eeeeee')->set('colorBg', '#111111')->assertSet('lowContrast', true)
            ->call('generate')->assertHasErrors('colors')->assertSet('dataUri', null);
    }

    public function test_template_change_resets_colors(): void
    {
        Livewire::actingAs(User::factory()->pro()->create())->test(QrCode::class)
            ->set('colorDark', '#222222')
            ->set('template', 'soft')
            ->assertSet('colorDark', '#6b1f2e')
            ->assertSet('colorBg', '#fbf6ec')
            ->set('colorBg', '#ffffff')
            ->call('resetColors')
            ->assertSet('colorBg', '#fbf6ec');
    }
}
