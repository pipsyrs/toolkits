<?php

namespace Tests\Feature;

use App\Actions\Support\SubmitSupportReport;
use App\Enums\SupportReportStatus;
use App\Livewire\Admin\Reports\Index as AdminReports;
use App\Livewire\Client\Support\Index as ClientSupport;
use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\SupportReport;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupportReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function role(string $name, array $permissions): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'web'])->givePermissionTo($permissions);
    }

    private function report(User $user, string $subject = 'QRIS tidak terbaca'): SupportReport
    {
        return $user->supportReports()->create(['category' => 'billing', 'subject' => $subject, 'message' => 'Sudah bayar tapi status masih menunggu.']);
    }

    public function test_user_can_submit_and_only_sees_own_reports(): void
    {
        $user = User::factory()->create();
        $this->report(User::factory()->create(), 'Laporan orang lain');

        Livewire::actingAs($user)->test(ClientSupport::class)
            ->call('openCreate')
            ->assertSet('formOpen', true)
            ->set('category', 'tool')
            ->set('subject', 'Kompres PDF gagal')
            ->set('message', 'File 3 MB selalu error di langkah terakhir.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('subject', '')
            ->assertSet('formOpen', false)
            ->assertSee('Kompres PDF gagal')
            ->assertDontSee('Laporan orang lain');

        $report = $user->supportReports()->sole();
        $this->assertSame(SupportReportStatus::Open, $report->fresh()->status);

        $this->actingAs($user)->get('/dashboard/support')->assertOk()->assertSee('Bantuan');
    }

    public function test_submit_validates_input(): void
    {
        Livewire::actingAs(User::factory()->create())->test(ClientSupport::class)
            ->set('category', 'unknown')
            ->set('subject', '')
            ->set('message', 'pendek')
            ->call('submit')
            ->assertHasErrors(['category', 'subject', 'message']);

        $this->assertSame(0, SupportReport::count());
    }

    public function test_submit_is_rate_limited_per_user(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(ClientSupport::class);

        foreach (range(1, SubmitSupportReport::MAX_PER_HOUR + 1) as $i) {
            $component->set('category', 'other')->set('subject', "Laporan {$i}")->set('message', 'Isi laporan yang cukup panjang.')->call('submit');
        }

        $component->assertHasErrors('form')->assertSee('Terlalu banyak laporan');
        $this->assertSame(SubmitSupportReport::MAX_PER_HOUR, $user->supportReports()->count());
    }

    public function test_admin_page_requires_reports_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/reports')->assertForbidden();

        $other = User::factory()->create()->assignRole($this->role('finance', ['admin.access', 'payments.view']));
        $this->actingAs($other)->get('/admin/reports')->assertForbidden();
        Livewire::actingAs($other)->test(AdminReports::class)->assertForbidden();

        $this->report(User::factory()->create());
        $viewer = User::factory()->create()->assignRole($this->role('cs-viewer', ['admin.access', 'reports.view']));
        $this->actingAs($viewer)->get('/admin/reports')->assertOk()->assertSee('QRIS tidak terbaca');
    }

    public function test_viewer_without_manage_cannot_reply(): void
    {
        $report = $this->report(User::factory()->create());
        $viewer = User::factory()->create()->assignRole($this->role('cs-viewer', ['admin.access', 'reports.view']));

        Livewire::actingAs($viewer)->test(AdminReports::class)
            ->call('open', $report->id)
            ->assertDontSee('Simpan tanggapan')
            ->set('replyStatus', 'resolved')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(SupportReportStatus::Open, $report->fresh()->status);
    }

    public function test_agent_replies_and_user_sees_reply(): void
    {
        $user = User::factory()->create();
        $report = $this->report($user);
        $agent = User::factory()->create()->assignRole($this->role('cs', ['admin.access', 'reports.view', 'reports.manage']));

        Livewire::actingAs($agent)->test(AdminReports::class)
            ->call('open', $report->id)
            ->assertSet('detailOpen', true)
            ->assertSet('replyStatus', 'open')
            ->set('replyStatus', 'resolved')
            ->set('reply', 'Pembayaran sudah kami konfirmasi manual.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('detailOpen', false);

        $report->refresh();
        $this->assertSame(SupportReportStatus::Resolved, $report->status);
        $this->assertSame($agent->id, $report->handled_by);
        $this->assertNotNull($report->resolved_at);
        $this->assertSame(1, AdminAuditLog::where('action', 'support_report.update')->count());

        Livewire::actingAs($user)->test(ClientSupport::class)->assertSee('Pembayaran sudah kami konfirmasi manual.')->assertSee('Selesai');

        // Reopening clears the resolution time.
        Livewire::actingAs($agent)->test(AdminReports::class)->call('open', $report->id)->set('replyStatus', 'in_progress')->call('save');
        $this->assertNull($report->fresh()->resolved_at);
    }

    public function test_filters_and_invalid_input(): void
    {
        $agent = User::factory()->create()->assignRole('superadmin');
        $owner = User::factory()->create(['email' => 'pelapor@example.test']);
        $this->report($owner, 'Masalah login');
        $closed = $this->report(User::factory()->create(), 'Sudah beres');
        $closed->forceFill(['status' => SupportReportStatus::Closed])->save();

        Livewire::actingAs($agent)->test(AdminReports::class)
            ->set('status', 'closed')->assertSee('Sudah beres')->assertDontSee('Masalah login')
            ->set('status', '')->set('search', 'pelapor@')->assertSee('Masalah login')->assertDontSee('Sudah beres')
            ->call('open', 'not-a-uuid')->assertHasErrors('report')->assertSet('selectedId', null);

        Livewire::actingAs($agent)->test(AdminReports::class)
            ->call('open', $closed->id)->set('replyStatus', 'bogus')->call('save')->assertHasErrors('status');
    }

    public function test_sidebar_groups_follow_permissions(): void
    {
        $agent = User::factory()->create()->assignRole($this->role('cs', ['admin.access', 'reports.view']));

        $this->actingAs($agent)->get('/admin/reports')->assertOk()
            ->assertSee('Customer service')
            ->assertDontSee('Master data')
            ->assertDontSee('Tipe metode bayar');

        $this->actingAs(User::factory()->create()->assignRole('superadmin'))->get('/admin')->assertOk()
            ->assertSeeInOrder([
                'Utama', 'Dashboard',
                'Konten', 'Links',
                'Penjualan', 'Plans', 'Transaksi', 'Metode bayar',
                'Tool', 'Alat',
                'Master data', 'Tipe metode bayar', 'Payment channel', 'Kategori alat',
                'Customer service', 'Reports',
                'Sistem', 'Users', 'Audit log', 'Role', 'Pengaturan',
            ]);
    }
}
