<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureNotBlocked;
use App\Livewire\Admin;
use App\Models\AdminAuditLog;
use App\Models\Link;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('superadmin');
    }

    public function test_client_gets_403_on_every_admin_route(): void
    {
        $client = User::factory()->create();

        foreach (['/admin', '/admin/users', '/admin/links', '/admin/plans', '/admin/audit-logs'] as $uri) {
            $this->actingAs($client)->get($uri)->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_superadmin_login_redirects_to_admin(): void
    {
        $admin = $this->superadmin();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_client_login_redirects_to_dashboard(): void
    {
        $client = User::factory()->create();

        $this->post('/login', ['email' => $client->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_superadmin_can_open_admin_and_client_area(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
    }

    public function test_blocked_user_cannot_login(): void
    {
        $user = User::factory()->blocked()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_blocked_user_active_session_is_kicked(): void
    {
        $user = User::factory()->blocked()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_persistent_middleware_is_registered(): void
    {
        $persistent = Livewire::getPersistentMiddleware();

        $this->assertContains(PermissionMiddleware::class, $persistent);
        $this->assertContains(EnsureNotBlocked::class, $persistent);
        $this->assertContains(EnsureEmailIsVerified::class, $persistent);
    }

    public function test_client_cannot_use_admin_livewire_components(): void
    {
        $client = User::factory()->create();
        $target = User::factory()->create();

        Livewire::actingAs($client)->test(Admin\Users\Index::class)->assertForbidden();
        Livewire::actingAs($client)->test(Admin\Links\Index::class)->assertForbidden();
        Livewire::actingAs($client)->test(Admin\Plans\Limits::class)->assertForbidden();

        $this->assertNull($target->fresh()->blocked_at);
    }

    public function test_superadmin_blocks_user_and_kicks_sessions(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'sess-1', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time(),
        ]);

        Livewire::actingAs($admin)->test(Admin\Users\Index::class)
            ->call('block', $target->id, 'Spam link')
            ->assertHasNoErrors();

        $this->assertNotNull($target->fresh()->blocked_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-1']);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.block', 'subject_id' => $target->id]);
    }

    public function test_superadmin_cannot_block_self_or_other_superadmin(): void
    {
        $admin = $this->superadmin();
        $other = $this->superadmin();

        Livewire::actingAs($admin)->test(Admin\Users\Index::class)
            ->call('block', $admin->id, 'Test alasan')
            ->assertForbidden();

        Livewire::actingAs($admin)->test(Admin\Users\Index::class)
            ->call('block', $other->id, 'Test alasan')
            ->assertForbidden();

        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_superadmin_disables_link(): void
    {
        $admin = $this->superadmin();
        $link = Link::factory()->create();

        Livewire::actingAs($admin)->test(Admin\Links\Index::class)
            ->call('disable', $link->id, 'Phishing')
            ->assertHasNoErrors();

        $this->assertNotNull($link->fresh()->disabled_at);
    }

    public function test_livewire_update_request_reapplies_route_middleware(): void
    {
        $admin = $this->superadmin();
        $client = User::factory()->create();
        $target = User::factory()->create();

        $html = $this->actingAs($admin)->get('/admin/users')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m, 'snapshot not found');
        $snapshot = html_entity_decode($m[1], ENT_QUOTES);

        $payload = fn () => ['components' => [[
            'snapshot' => $snapshot,
            'updates' => [],
            'calls' => [['method' => 'block', 'params' => [$target->id, 'Spam link'], 'metadata' => []]],
        ]]];
        $uri = app('livewire')->getUpdateUri();

        // Client replays the admin snapshot → role middleware 403.
        $this->actingAs($client)->withHeader('X-Livewire', 'true')->postJson($uri, $payload())->assertForbidden();

        // Superadmin blocked mid-session → EnsureNotBlocked runs on update.
        $admin->forceFill(['blocked_at' => now()])->save();
        $this->actingAs($admin->fresh())->withHeader('X-Livewire', 'true')->postJson($uri, $payload())->assertRedirect(route('login'));

        $this->assertNull($target->fresh()->blocked_at);
    }

    public function test_register_is_throttled_per_ip(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/register', ['email' => 'x'])->assertStatus(302);
        }

        $this->post('/register', ['email' => 'x'])->assertStatus(429);
    }
}
