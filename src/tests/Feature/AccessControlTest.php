<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
    }

    // Snapshot of a full-page Livewire component rendered for the superadmin.
    private function adminSnapshot(string $uri): string
    {
        $admin = User::factory()->create()->assignRole('superadmin');
        $html = $this->actingAs($admin)->get($uri)->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m, "snapshot not found on {$uri}");

        return html_entity_decode($m[1], ENT_QUOTES);
    }

    public function test_client_replaying_any_admin_snapshot_gets_403(): void
    {
        $client = User::factory()->create();
        $uri = app('livewire')->getUpdateUri();

        foreach (['/admin', '/admin/links', '/admin/plans', '/admin/audit-logs'] as $page) {
            $payload = ['components' => [['snapshot' => $this->adminSnapshot($page), 'updates' => [], 'calls' => [
                ['method' => '$refresh', 'params' => [], 'metadata' => []],
            ]]]];

            $this->actingAs($client)->withHeader('X-Livewire', 'true')->postJson($uri, $payload)->assertForbidden();
        }
    }

    public function test_guest_redirected_to_login_from_all_dashboard_and_admin_pages(): void
    {
        foreach (['/dashboard', '/dashboard/links', '/dashboard/files', '/dashboard/billing', '/dashboard/profile', '/admin', '/admin/users'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public function test_unverified_user_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_superadmin_cannot_open_admin(): void
    {
        $admin = User::factory()->unverified()->create()->assignRole('superadmin');

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('verification.notice'));
    }

    public function test_superadmin_login_ignores_non_admin_intended_url(): void
    {
        $admin = User::factory()->create()->assignRole('superadmin');

        $this->get('/dashboard/links');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_superadmin_login_honours_admin_intended_url(): void
    {
        $admin = User::factory()->create()->assignRole('superadmin');

        $this->get('/admin/users');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin/users');
    }

    public function test_client_login_honours_intended_url(): void
    {
        $user = User::factory()->create();

        $this->get('/dashboard/links');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard/links');
    }

    public function test_client_does_not_see_admin_link_but_superadmin_does(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertDontSee(route('admin.dashboard'), false);

        $this->actingAs(User::factory()->create()->assignRole('superadmin'))->get('/dashboard')->assertSee(route('admin.dashboard'), false);
    }

    public function test_wrong_password_rejected_without_enumeration(): void
    {
        $user = User::factory()->create();

        $known = $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $knownMsg = session('errors')->first('email');
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');

        $this->assertSame($knownMsg, session('errors')->first('email'));
        $this->assertGuest();
    }
}
