<?php

namespace Tests\Feature;

use App\Actions\Admin\AssignUserRoles;
use App\Actions\Admin\BlockUser;
use App\Actions\Admin\DeleteRole;
use App\Actions\Admin\DisableLink;
use App\Actions\Admin\SaveRole;
use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\AdminAuditLog;
use App\Models\Link;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTES = [
        '/admin/users' => 'users.view',
        '/admin/links' => 'links.view',
        '/admin/plans' => 'plans.manage',
        '/admin/audit-logs' => 'audit.view',
        '/admin/settings' => 'settings.manage',
        '/admin/roles' => 'roles.manage',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PlanSeeder::class, RoleSeeder::class]);
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole(User::SUPERADMIN_ROLE);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithRole(string $role, array $permissions): User
    {
        $role = Role::findOrCreate($role, 'web');
        $role->syncPermissions($permissions);

        return User::factory()->create()->assignRole($role);
    }

    private function moderator(): User
    {
        return $this->userWithRole('moderator', ['admin.access', 'links.view', 'links.moderate']);
    }

    private function confirm(): void
    {
        session(['auth.password_confirmed_at' => time()]);
    }

    private function snapshot(string $html): string
    {
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m, 'snapshot not found');

        return html_entity_decode($m[1], ENT_QUOTES);
    }

    private function replay(User $user, string $snapshot, string $method, array $params = [])
    {
        return $this->actingAs($user)->withHeader('X-Livewire', 'true')->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [['method' => $method, 'params' => $params, 'metadata' => []]],
            ]],
        ]);
    }

    public function test_access_admin_gate_is_removed(): void
    {
        $this->assertFalse(Gate::has('access-admin'));
    }

    public function test_each_admin_route_requires_its_permission(): void
    {
        $session = ['auth.password_confirmed_at' => time()];

        foreach (self::ROUTES as $uri => $permission) {
            $allowed = $this->userWithRole('r-'.str_replace('.', '-', $permission), ['admin.access', $permission]);
            $this->actingAs($allowed)->withSession($session)->get($uri)->assertOk();

            $other = $permission === 'users.view' ? 'links.view' : 'users.view';
            $denied = $this->userWithRole('d-'.str_replace('.', '-', $permission), ['admin.access', $other]);
            $this->actingAs($denied)->withSession($session)->get($uri)->assertForbidden();
        }
    }

    public function test_admin_access_is_required_even_with_feature_permission(): void
    {
        $user = $this->userWithRole('no-admin', ['links.view']);

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs($user)->get('/admin/links')->assertForbidden();
    }

    public function test_moderator_scope_and_livewire_replay(): void
    {
        $admin = $this->superadmin();
        $moderator = $this->moderator();
        $session = ['auth.password_confirmed_at' => time()];

        $this->actingAs($moderator)->get('/admin')->assertOk();
        $this->actingAs($moderator)->get('/admin/links')->assertOk();

        foreach (['/admin/users', '/admin/settings', '/admin/roles', '/admin/plans', '/admin/audit-logs'] as $uri) {
            $this->actingAs($moderator)->withSession($session)->get($uri)->assertForbidden();
        }

        $target = User::factory()->create();
        $usersSnapshot = $this->snapshot($this->actingAs($admin)->get('/admin/users')->assertOk()->getContent());
        $this->replay($moderator, $usersSnapshot, 'block', [$target->id, 'Spam link'])->assertForbidden();
        $this->assertNull($target->fresh()->blocked_at);

        $role = Role::findOrCreate('victim', 'web');
        $rolesSnapshot = $this->snapshot($this->actingAs($admin)->withSession($session)->get('/admin/roles')->assertOk()->getContent());
        $this->replay($moderator, $rolesSnapshot, 'delete', [$role->id])->assertForbidden();
        $this->assertNotNull(Role::find($role->id));
    }

    public function test_menu_follows_permissions(): void
    {
        $this->actingAs($this->moderator());
        $html = Blade::render('<x-layouts::admin>isi</x-layouts::admin>');

        $this->assertStringContainsString(route('admin.links.index'), $html);
        foreach (['admin.users.index', 'admin.settings.edit', 'admin.roles.index', 'admin.plans.edit', 'admin.audit-logs.index'] as $name) {
            $this->assertStringNotContainsString('href="'.route($name).'"', $html);
        }

        $this->actingAs($this->superadmin());
        $html = Blade::render('<x-layouts::admin>isi</x-layouts::admin>');
        $this->assertStringContainsString(route('admin.roles.index'), $html);
    }

    public function test_link_moderation_uses_permission(): void
    {
        $link = Link::factory()->create();

        app(DisableLink::class)->handle($this->moderator(), $link, 'Phishing', null);
        $this->assertNotNull($link->fresh()->disabled_at);

        $viewer = $this->userWithRole('link-viewer', ['admin.access', 'links.view']);
        $this->expectException(AuthorizationException::class);
        app(DisableLink::class)->handle($viewer, Link::factory()->create(), 'Phishing', null);
    }

    public function test_superadmin_cannot_delete_other_users_link_in_client_area(): void
    {
        $link = Link::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->assertFalse(Gate::forUser($this->superadmin())->allows('delete', $link));
    }

    public function test_login_with_admin_access_goes_to_admin(): void
    {
        $moderator = $this->moderator();

        $this->post('/login', ['email' => $moderator->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_cannot_create_role_with_permissions_actor_lacks(): void
    {
        $manager = $this->userWithRole('role-manager', ['admin.access', 'roles.manage', 'links.view']);
        $this->confirm();

        try {
            app(SaveRole::class)->handle($manager, null, 'escalate', ['settings.manage'], null);
            $this->fail('escalation allowed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permissions', $e->errors());
        }

        $role = app(SaveRole::class)->handle($manager, null, 'viewer', ['links.view'], '127.0.0.1');
        $this->assertEqualsCanonicalizing(['admin.access', 'links.view'], $role->permissions->pluck('name')->all());
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'role.created', 'subject_id' => $role->id]);
    }

    public function test_role_name_rules(): void
    {
        $admin = $this->superadmin();
        $this->confirm();

        foreach (['superadmin', 'AB', 'Bad Name', 'x'] as $name) {
            try {
                app(SaveRole::class)->handle($admin, null, $name, [], null);
                $this->fail("name {$name} accepted");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('name', $e->errors());
            }
        }

        $this->expectException(ValidationException::class);
        app(SaveRole::class)->handle($admin, null, 'valid-role', ['not.in.catalog'], null);
    }

    public function test_superadmin_role_is_locked(): void
    {
        $admin = $this->superadmin();
        $super = Role::findByName(User::SUPERADMIN_ROLE, 'web');
        $this->confirm();

        $this->assertFalse($admin->can('update', $super));
        $this->assertFalse($admin->can('delete', $super));

        $this->expectException(AuthorizationException::class);
        app(SaveRole::class)->handle($admin, $super, 'superadmin', [], null);
    }

    public function test_superadmin_role_cannot_be_deleted(): void
    {
        $this->confirm();

        $this->expectException(AuthorizationException::class);
        app(DeleteRole::class)->handle($this->superadmin(), Role::findByName(User::SUPERADMIN_ROLE, 'web'), null);
    }

    public function test_cannot_edit_own_role_or_role_with_more_permissions(): void
    {
        $manager = $this->userWithRole('role-manager', ['admin.access', 'roles.manage']);
        $bigger = Role::findOrCreate('bigger', 'web');
        $bigger->syncPermissions(['admin.access', 'settings.manage']);
        $this->confirm();

        $this->assertFalse($manager->can('update', Role::findByName('role-manager', 'web')));
        $this->assertFalse($manager->can('update', $bigger));

        $this->expectException(AuthorizationException::class);
        app(SaveRole::class)->handle($manager, Role::findByName('role-manager', 'web'), 'role-manager', ['admin.access', 'roles.manage', 'users.view'], null);
    }

    public function test_delete_role_in_use_is_rejected(): void
    {
        $admin = $this->superadmin();
        $this->moderator();
        $unused = Role::findOrCreate('unused', 'web');
        $this->confirm();

        try {
            app(DeleteRole::class)->handle($admin, Role::findByName('moderator', 'web'), null);
            $this->fail('role in use deleted');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('1 pengguna', $e->errors()['role'][0]);
        }

        app(DeleteRole::class)->handle($admin, $unused, null);
        $this->assertNull(Role::find($unused->id));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'role.deleted']);
    }

    public function test_role_actions_require_recent_password_confirmation(): void
    {
        $admin = $this->superadmin();
        session(['auth.password_confirmed_at' => time() - 1000]);

        $this->actingAs($admin);
        Livewire::test(RolesIndex::class)->call('openCreate')->set('name', 'new-role')->call('save')->assertRedirect(route('password.confirm'));
        $this->assertNull(Role::where('name', 'new-role')->first());

        $this->expectException(AuthorizationException::class);
        app(SaveRole::class)->handle($admin, null, 'new-role', [], null);
    }

    public function test_livewire_role_form_creates_and_maps_errors(): void
    {
        $manager = $this->userWithRole('role-manager', ['admin.access', 'roles.manage', 'links.view']);
        $this->actingAs($manager);
        $this->confirm();

        Livewire::test(RolesIndex::class)
            ->call('openCreate')
            ->set('name', 'escalate')
            ->set('selected', ['settings.manage'])
            ->call('save')
            ->assertHasErrors('selected')
            ->assertSet('formOpen', true);

        Livewire::test(RolesIndex::class)
            ->call('openCreate')
            ->set('name', 'link-viewer')
            ->set('selected', ['links.view'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false)
            ->assertNoRedirect();

        $this->assertTrue(Role::findByName('link-viewer', 'web')->hasPermissionTo('links.view'));

        Livewire::test(RolesIndex::class)->assertViewHas('editable', fn ($e) => $e[Role::findByName('link-viewer', 'web')->id] === true
            && $e[Role::findByName('role-manager', 'web')->id] === false
            && $e[Role::findByName(User::SUPERADMIN_ROLE, 'web')->id] === false);

        // Edit in the same modal: loads the role, saves without leaving the list.
        $linkViewer = Role::findByName('link-viewer', 'web');
        Livewire::test(RolesIndex::class)
            ->call('openEdit', $linkViewer->id)
            ->assertSet('editingId', $linkViewer->id)
            ->assertSet('name', 'link-viewer')
            ->set('name', 'link-reader')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false);
        $this->assertSame('link-reader', $linkViewer->fresh()->name);
    }

    public function test_cannot_assign_roles_to_self(): void
    {
        $admin = $this->superadmin();
        $this->confirm();

        $this->expectException(AuthorizationException::class);
        app(AssignUserRoles::class)->handle($admin, $admin, [], null);
    }

    public function test_non_superadmin_cannot_grant_superadmin_or_bigger_role(): void
    {
        $assigner = $this->userWithRole('assigner', ['admin.access', 'users.view', 'users.roles']);
        $target = User::factory()->create();
        $super = Role::findByName(User::SUPERADMIN_ROLE, 'web');
        $bigger = Role::findOrCreate('bigger', 'web');
        $bigger->syncPermissions(['admin.access', 'settings.manage']);
        $this->confirm();

        foreach ([$super, $bigger] as $role) {
            try {
                app(AssignUserRoles::class)->handle($assigner, $target, [$role->id], null);
                $this->fail("granted {$role->name}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('roles', $e->errors());
            }
        }

        $this->assertCount(0, $target->fresh()->roles);
    }

    public function test_non_superadmin_cannot_change_superadmin_target(): void
    {
        $assigner = $this->userWithRole('assigner', ['admin.access', 'users.view', 'users.roles']);
        $this->confirm();

        $this->expectException(AuthorizationException::class);
        app(AssignUserRoles::class)->handle($assigner, $this->superadmin(), [], null);
    }

    public function test_cannot_change_admin_with_more_permissions(): void
    {
        $assigner = $this->userWithRole('assigner', ['admin.access', 'users.view', 'users.roles', 'users.block']);
        $bigger = $this->userWithRole('bigger', ['admin.access', 'settings.manage']);
        $this->confirm();

        $this->assertFalse($assigner->can('assignRoles', $bigger));
        $this->assertFalse($assigner->can('block', $bigger));

        $this->expectException(AuthorizationException::class);
        app(BlockUser::class)->handle($assigner, $bigger, 'Spam link', null);
    }

    public function test_last_superadmin_cannot_be_revoked(): void
    {
        $a = $this->superadmin();
        $b = $this->superadmin();
        $this->confirm();

        // Two superadmins: revoking one is allowed.
        app(AssignUserRoles::class)->handle($a, $b, [], null);
        $this->assertFalse($b->fresh()->isSuperadmin());

        // Race: actor demoted concurrently, target is the last row.
        $c = $this->superadmin();
        $a->load('roles');
        DB::table(config('permission.table_names.model_has_roles'))->where('model_id', $a->id)->delete();

        try {
            app(AssignUserRoles::class)->handle($a, $c, [], null);
            $this->fail('last superadmin revoked');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('terakhir', $e->errors()['roles'][0]);
        }

        $this->assertTrue($c->fresh()->isSuperadmin());
    }

    public function test_assign_subset_role_via_livewire_with_audit(): void
    {
        $assigner = $this->userWithRole('assigner', ['admin.access', 'users.view', 'users.roles', 'links.view']);
        $viewer = Role::findOrCreate('link-viewer', 'web');
        $viewer->syncPermissions(['admin.access', 'links.view']);
        $target = User::factory()->create();

        $this->actingAs($assigner);
        $this->confirm();

        Livewire::test(UsersIndex::class)
            ->assertViewHas('assignableRoles', fn ($roles) => $roles->pluck('name')->sort()->values()->all() === ['assigner', 'link-viewer'])
            ->call('editRoles', $target->id)
            ->assertSet('editingUserId', $target->id)
            ->set('roleSelection', [$viewer->id])
            ->call('saveRoles')
            ->assertHasNoErrors()
            ->assertSet('editingUserId', null);

        $this->assertTrue($target->fresh()->hasRole('link-viewer'));
        $log = AdminAuditLog::where('action', 'user.roles_updated')->firstOrFail();
        $this->assertSame(['added' => ['link-viewer'], 'removed' => []], $log->changes);

        // Permission cache refreshed: target now reaches /admin/links.
        $this->actingAs($target->fresh())->get('/admin/links')->assertOk();
    }

    public function test_assign_roles_without_permission_is_forbidden_in_livewire(): void
    {
        $viewerOnly = $this->userWithRole('users-viewer', ['admin.access', 'users.view']);
        $target = User::factory()->create();
        $this->actingAs($viewerOnly);
        $this->confirm();

        Livewire::test(UsersIndex::class)
            ->assertViewHas('assignableRoles', fn ($roles) => $roles->isEmpty())
            ->call('editRoles', $target->id)
            ->assertForbidden();
    }
}
