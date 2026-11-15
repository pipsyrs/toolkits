<?php

namespace Tests\Feature;

use App\Livewire\Admin;
use App\Models\AdminAuditLog;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminActionsTest extends TestCase
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

    private function freeLimits(): array
    {
        return Plan::where('slug', 'free')->first()->limits;
    }

    public function test_unblock_user_restores_access_and_is_audited(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->blocked()->create();

        Livewire::actingAs($admin)->test(Admin\Users\Index::class)
            ->call('unblock', $target->id)
            ->assertHasNoErrors();

        $fresh = $target->fresh();
        $this->assertNull($fresh->blocked_at);
        $this->assertNull($fresh->blocked_reason);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'user.unblock', 'admin_id' => $admin->id, 'subject_id' => $target->id, 'subject_type' => User::class,
        ]);

        $this->post('/logout');
        $this->post('/login', ['email' => $target->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    public function test_block_audit_log_records_reason_ip_and_admin(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create();

        Livewire::actingAs($admin)->test(Admin\Users\Index::class)->call('block', $target->id, '  Spam link  ');

        $log = AdminAuditLog::firstOrFail();
        $this->assertSame('Spam link', $log->reason);
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertNotNull($log->ip);
        $this->assertTrue($log->subject->is($target));
        $this->assertSame('Spam link', $target->fresh()->blocked_reason);
    }

    public function test_block_requires_reason(): void
    {
        $target = User::factory()->create();

        Livewire::actingAs($this->superadmin())->test(Admin\Users\Index::class)
            ->call('block', $target->id, ' a ')
            ->assertHasErrors('reason');

        $this->assertNull($target->fresh()->blocked_at);
    }

    public function test_block_with_invalid_id_does_not_crash(): void
    {
        Livewire::actingAs($this->superadmin())->test(Admin\Users\Index::class)
            ->call('block', "1' OR '1'='1", 'Spam link')
            ->assertHasErrors('userId');

        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_client_cannot_unblock_or_enable(): void
    {
        $client = User::factory()->create();
        $blocked = User::factory()->blocked()->create();
        $link = Link::factory()->disabled()->create();

        Livewire::actingAs($client)->test(Admin\Users\Index::class)->assertForbidden();
        Livewire::actingAs($client)->test(Admin\AuditLogs\Index::class)->assertForbidden();
        Livewire::actingAs($client)->test(Admin\Overview::class)->assertForbidden();

        $this->assertNotNull($blocked->fresh()->blocked_at);
        $this->assertNotNull($link->fresh()->disabled_at);
    }

    public function test_enable_link_restores_redirect_and_is_audited(): void
    {
        $admin = $this->superadmin();
        $link = Link::factory()->disabled()->create();

        $this->get('/s/'.$link->code)->assertNotFound();

        Livewire::actingAs($admin)->test(Admin\Links\Index::class)->call('enable', $link->id)->assertHasNoErrors();

        $this->assertNull($link->fresh()->disabled_at);
        $this->get('/s/'.$link->code)->assertStatus(302);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'link.enable', 'subject_id' => $link->id]);
    }

    public function test_disable_link_is_audited_with_reason(): void
    {
        $link = Link::factory()->create();

        Livewire::actingAs($this->superadmin())->test(Admin\Links\Index::class)->call('disable', $link->id, 'Phishing');

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'link.disable', 'subject_id' => $link->id, 'reason' => 'Phishing']);
        $this->assertSame('Phishing', $link->fresh()->disabled_reason);
    }

    public function test_audit_log_filter_by_action(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create();
        Livewire::actingAs($admin)->test(Admin\Users\Index::class)->call('block', $target->id, 'Alasan blok');
        Livewire::actingAs($admin)->test(Admin\Users\Index::class)->call('unblock', $target->id);

        $logs = Livewire::actingAs($admin)->test(Admin\AuditLogs\Index::class)
            ->set('action', 'user.unblock')
            ->viewData('logs');

        $this->assertSame(['user.unblock'], collect($logs->items())->pluck('action')->all());
    }

    public function test_users_filter_by_status(): void
    {
        $admin = $this->superadmin();
        $blocked = User::factory()->blocked()->create();
        User::factory()->create();

        $users = Livewire::actingAs($admin)->test(Admin\Users\Index::class)->set('status', 'blocked')->viewData('users');

        $this->assertSame([$blocked->id], collect($users->items())->pluck('id')->all());
    }
}
