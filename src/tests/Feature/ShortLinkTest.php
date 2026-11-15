<?php

namespace Tests\Feature;

use App\Actions\Links\CreateShortLink;
use App\Exceptions\QuotaExceededException;
use App\Livewire\Client\Links\Index as ClientLinks;
use App\Livewire\Tools\Shorten;
use App\Models\BlockedDomain;
use App\Models\Link;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    private string $guestId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        config(['app.url' => 'https://toolkits.test']);
        $this->guestId = (string) Str::uuid();
    }

    private function create(?User $user, array $data, string $ip = '203.0.113.5'): Link
    {
        return app(CreateShortLink::class)->handle($user, $this->guestId, $ip, $data);
    }

    private function assertRejected(?User $user, array $data, string $field): void
    {
        try {
            $this->create($user, $data);
            $this->fail('Expected validation error on '.$field);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_rejects_unsafe_targets(): void
    {
        BlockedDomain::create(['domain' => 'evil.com']);

        $this->assertRejected(null, ['target_url' => 'javascript:alert(1)'], 'target_url');
        $this->assertRejected(null, ['target_url' => 'https://toolkits.test/admin'], 'target_url');
        $this->assertRejected(null, ['target_url' => 'http://127.0.0.1/x'], 'target_url');
        $this->assertRejected(null, ['target_url' => 'http://localhost/x'], 'target_url');
        $this->assertRejected(null, ['target_url' => 'https://sub.evil.com/x'], 'target_url');
        $this->assertRejected(null, ['target_url' => 'https://user:pw@example.com'], 'target_url');
    }

    // Visitors without an account follow Free (permanent links, 20 per day).
    public function test_guest_link_follows_free_plan_and_has_lowercase_code(): void
    {
        $link = $this->create(null, ['target_url' => 'https://example.com/a']);

        $this->assertSame('guest', $link->created_tier);
        $this->assertSame($this->guestId, $link->guest_id);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{7}$/', $link->code);
        $this->assertNull($link->expires_at);
    }

    public function test_guest_quota_follows_free_plan(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->create(null, ['target_url' => 'https://example.com/'.$i]);
        }

        $this->expectException(QuotaExceededException::class);
        $this->create(null, ['target_url' => 'https://example.com/21']);
    }

    public function test_guest_cannot_use_alias_and_reserved_alias_rejected(): void
    {
        $this->assertRejected(null, ['target_url' => 'https://example.com', 'alias' => 'promo'], 'alias');
        $this->assertRejected(User::factory()->create(), ['target_url' => 'https://example.com', 'alias' => 'admin'], 'alias');
    }

    public function test_free_sixth_alias_rejected(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->create($user, ['target_url' => 'https://example.com/'.$i, 'alias' => 'promo-'.$i]);
        }

        $this->assertRejected($user, ['target_url' => 'https://example.com/6', 'alias' => 'promo-6'], 'alias');
    }

    public function test_shorten_component_maps_errors_and_shows_result(): void
    {
        Livewire::test(Shorten::class)
            ->set('targetUrl', 'javascript:alert(1)')
            ->call('shorten')
            ->assertHasErrors('targetUrl');

        Livewire::actingAs(User::factory()->create())->test(Shorten::class)
            ->set('targetUrl', 'https://example.com/ok')
            ->call('shorten')
            ->assertHasNoErrors()
            ->assertSet('result.short_url', fn ($url) => str_contains((string) $url, '/s/'));
    }

    public function test_redirect_behaviour(): void
    {
        $guest = Link::factory()->guest()->create();
        $free = Link::factory()->create();
        $expired = Link::factory()->expired()->create();
        $disabled = Link::factory()->disabled()->create();

        // Free has no interstitial by default, also for links made without an account.
        $this->get('/s/'.$guest->code)->assertRedirect($guest->target_url);
        $this->get('/s/'.strtoupper($free->code))->assertRedirect($free->target_url)->assertStatus(302);
        $this->get('/s/'.$expired->code)->assertStatus(410);
        $this->get('/s/'.$disabled->code)->assertNotFound();
        $this->get('/s/nope-404')->assertNotFound();

        $this->assertSame(1, $free->fresh()->clicks_total);
    }

    public function test_bot_click_not_counted(): void
    {
        $link = Link::factory()->create();

        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/s/'.$link->code)->assertStatus(302);

        $this->assertSame(0, $link->fresh()->clicks_total);
    }

    public function test_user_cannot_delete_other_users_link(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $link = Link::factory()->for($owner)->create();

        Livewire::actingAs($other)->test(ClientLinks::class)
            ->call('delete', $link->id)
            ->assertForbidden();

        $this->assertModelExists($link);

        Livewire::actingAs($owner)->test(ClientLinks::class)->call('delete', $link->id);
        $this->assertModelMissing($link);
    }

    public function test_user_id_is_uuid_v7(): void
    {
        $id = User::factory()->create()->id;

        $this->assertTrue(Str::isUuid($id));
        $this->assertSame('7', $id[14]);
    }
}
