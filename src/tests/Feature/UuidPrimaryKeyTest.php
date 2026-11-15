<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\BlockedDomain;
use App\Models\Link;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\Quota\QuotaService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UuidPrimaryKeyTest extends TestCase
{
    use RefreshDatabase;

    private function assertUuidV7(Model $model): void
    {
        $this->assertFalse($model->getIncrementing(), $model::class.' must not increment');
        $this->assertSame('string', $model->getKeyType(), $model::class);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $model->getKey(),
            $model::class.' id is not uuid v7',
        );
    }

    public function test_custom_models_use_uuid_v7(): void
    {
        $this->seed([PlanSeeder::class, RoleSeeder::class]);
        $user = User::factory()->create();

        $this->assertUuidV7($user);
        $this->assertUuidV7(Link::factory()->for($user)->create());
        $this->assertUuidV7(Plan::firstOrFail());
        $this->assertUuidV7(Role::firstOrFail());
        $this->assertUuidV7(Permission::create(['name' => 'test.perm', 'guard_name' => 'web']));
        $this->assertUuidV7(BlockedDomain::create(['domain' => 'evil.com', 'created_by' => $user->id]));
        $this->assertUuidV7(UsageCounter::create(['subject' => 'u:x', 'metric' => 'links', 'period_date' => now()->toDateString(), 'used' => 0]));
        $this->assertUuidV7(AdminAuditLog::create(['admin_id' => $user->id, 'action' => 'test', 'subject_type' => User::class, 'subject_id' => $user->id]));
    }

    public function test_quota_counter_rows_use_uuid_v7(): void
    {
        app(QuotaService::class)->reserve(['u:y'], 'links', 1, ['u:y' => 5]);

        $this->assertSame('7', DB::table('usage_counters')->value('id')[14]);
    }

    public function test_role_pivot_stores_uuid_model_id(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create()->assignRole('superadmin');

        $row = DB::table('model_has_roles')->first();
        $this->assertSame($user->id, $row->model_id);
        $this->assertSame(Role::findByName('superadmin')->id, $row->role_id);
    }

    public function test_jobs_tables_keep_integer_ids(): void
    {
        $this->assertSame('integer', Schema::getColumnType('jobs', 'id'));
        $this->assertSame('integer', Schema::getColumnType('failed_jobs', 'id'));
    }
}
