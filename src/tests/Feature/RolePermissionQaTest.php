<?php

namespace Tests\Feature;

use App\Actions\Admin\AssignUserRoles;
use App\Actions\Admin\DeleteRole;
use App\Actions\Admin\SaveRole;
use App\Enums\PermissionName;
use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Client\Links\Index as ClientLinks;
use App\Models\AdminAuditLog;
use App\Models\Link;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class RolePermissionQaTest extends TestCase
{
    use RefreshDatabase;

    // route name => permission required (besides admin.access)
    private const MENU = [
        'admin.users.index' => 'users.view',
        'admin.links.index' => 'links.view',
        'admin.plans.edit' => 'plans.manage',
        'admin.audit-logs.index' => 'audit.view',
        'admin.roles.index' => 'roles.manage',
        'admin.settings.edit' => 'settings.manage',
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
    private function role(string $name, array $permissions): Role
    {
        $role = Role::findOrCreate($name, 'web');
        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithRole(string $name, array $permissions): User
    {
        return User::factory()->create()->assignRole($this->role($name, $permissions));
    }

    /**
     * @return list<string>
     */
    private function featurePermissions(): array
    {
        return array_values(array_diff(PermissionName::values(), ['admin.access']));
    }

    private function confirmed(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    private function snapshotOf(User $user, string $uri): string
    {
        $html = $this->actingAs($user)->withSession($this->confirmed())->get($uri)->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m, "snapshot not found on {$uri}");

        return html_entity_decode($m[1], ENT_QUOTES);
    }

    private function replay(User $user, string $snapshot, ?string $method, array $params = [], array $updates = [])
    {
        $calls = $method === null ? [] : [['method' => $method, 'params' => $params, 'metadata' => []]];

        return $this->actingAs($user)->withSession($this->confirmed())->withHeader('X-Livewire', 'true')
            ->postJson(app('livewire')->getUpdateUri(), [
                'components' => [['snapshot' => $snapshot, 'updates' => (object) $updates, 'calls' => $calls]],
            ]);
    }

    // --- Matrix ---------------------------------------------------------------

    public function test_http_route_matrix_per_single_permission(): void
    {
        $routes = [
            '/admin' => 'admin.access',
            '/admin/users' => 'users.view',
            '/admin/links' => 'links.view',
            '/admin/plans' => 'plans.manage',
            '/admin/audit-logs' => 'audit.view',
            '/admin/settings' => 'settings.manage',
            '/admin/roles' => 'roles.manage',
        ];

        foreach (array_merge(['admin.access'], $this->featurePermissions()) as $permission) {
            $user = $this->userWithRole('only-'.str_replace('.', '-', $permission), array_unique(['admin.access', $permission]));

            foreach ($routes as $uri => $required) {
                $status = $this->actingAs($user)->withSession($this->confirmed())->get($uri)->status();
                $expected = in_array($required, ['admin.access', $permission], true) ? 200 : 403;
                $this->assertSame($expected, $status, "{$permission} -> {$uri}");
            }
        }
    }

    public function test_empty_role_cannot_enter_admin(): void
    {
        $user = $this->userWithRole('empty', []);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_menu_matrix_per_single_permission(): void
    {
        foreach (array_merge(['admin.access'], $this->featurePermissions()) as $permission) {
            $this->actingAs($this->userWithRole('m-'.str_replace('.', '-', $permission), array_unique(['admin.access', $permission])));
            $html = Blade::render('<x-layouts::admin>isi</x-layouts::admin>');

            $this->assertStringContainsString('href="'.route('admin.dashboard').'"', $html);

            foreach (self::MENU as $name => $required) {
                $visible = str_contains($html, 'href="'.route($name).'"');
                $this->assertSame($required === $permission, $visible, "{$permission} menu {$name}");
            }
        }
    }

    public function test_livewire_replay_matrix_forbids_missing_permissions(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create();
        $link = Link::factory()->create();
        $victimRole = $this->role('victim', []);

        // [uri, method, params, permissions needed]
        $actions = [
            ['/admin/users', 'block', [$target->id, 'Spam link'], ['users.view', 'users.block']],
            ['/admin/links', 'disable', [$link->id, 'Phishing'], ['links.view', 'links.moderate']],
            ['/admin/plans', 'save', [], ['plans.manage']],
            ['/admin/audit-logs', '$refresh', [], ['audit.view']],
            ['/admin/settings', 'save', [], ['settings.manage']],
            ['/admin/roles', 'delete', [$victimRole->id], ['roles.manage']],
            ['/admin/roles', 'save', [], ['roles.manage']],
        ];

        $snapshots = [];
        foreach (array_unique(array_column($actions, 0)) as $uri) {
            $snapshots[$uri] = $this->snapshotOf($admin, $uri);
        }

        foreach ($this->featurePermissions() as $permission) {
            $user = $this->userWithRole('lw-'.str_replace('.', '-', $permission), ['admin.access', $permission]);

            foreach ($actions as [$uri, $method, $params, $needed]) {
                if ($needed === [$permission]) {
                    continue;
                }

                $this->replay($user, $snapshots[$uri], $method, $params)
                    ->assertForbidden();
            }
        }

        $this->assertNull($target->fresh()->blocked_at);
        $this->assertNull($link->fresh()->disabled_at);
        $this->assertNotNull(Role::find($victimRole->id));
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_users_view_alone_cannot_open_or_save_roles_via_replay(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create();
        $super = Role::findByName(User::SUPERADMIN_ROLE, 'web');

        // Superadmin opens the modal; the snapshot now carries editingUserId.
        $opened = $this->replay($admin, $this->snapshotOf($admin, '/admin/users'), 'editRoles', [$target->id])->assertOk();
        $snapshot = $opened->json('components.0.snapshot');

        $viewer = $this->userWithRole('users-viewer', ['admin.access', 'users.view']);
        $this->replay($viewer, $snapshot, 'saveRoles', [], ['roleSelection' => [$super->id]])->assertForbidden();
        $this->replay($viewer, $snapshot, 'editRoles', [$target->id])->assertForbidden();

        $this->assertFalse($target->fresh()->isSuperadmin());
    }

    // --- Tampering --------------------------------------------------------------

    public function test_editing_user_id_is_locked_against_tampering(): void
    {
        $assigner = $this->userWithRole('assigner', ['admin.access', 'users.view', 'users.roles']);
        $normal = User::factory()->create();
        $superTarget = $this->superadmin();

        $opened = $this->replay($assigner, $this->snapshotOf($assigner, '/admin/users'), 'editRoles', [$normal->id])->assertOk();
        $snapshot = $opened->json('components.0.snapshot');

        $response = $this->replay($assigner, $snapshot, 'saveRoles', [], ['editingUserId' => $superTarget->id, 'roleSelection' => []]);
        $this->assertFalse($response->isSuccessful());

        // Forged snapshot data fails the checksum.
        $forged = json_decode($snapshot, true);
        $forged['data']['editingUserId'] = $superTarget->id;
        $this->assertFalse($this->replay($assigner, json_encode($forged), 'saveRoles')->isSuccessful());

        $this->assertTrue($superTarget->fresh()->isSuperadmin());
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_save_roles_rechecks_target_after_modal_opened(): void
    {
        $assigner = $this->userWithRole('assigner', ['admin.access', 'users.view', 'users.roles']);
        $target = User::factory()->create();

        $opened = $this->replay($assigner, $this->snapshotOf($assigner, '/admin/users'), 'editRoles', [$target->id])->assertOk();

        // Target promoted to a bigger admin after the modal was opened.
        $target->assignRole($this->role('bigger', ['admin.access', 'settings.manage']));

        $this->replay($assigner, $opened->json('components.0.snapshot'), 'saveRoles', [], ['roleSelection' => []])
            ->assertForbidden();
        $this->assertTrue($target->fresh()->hasRole('bigger'));
    }

    public function test_role_selection_tampering_is_rejected(): void
    {
        $assigner = $this->userWithRole('assigner', ['admin.access', 'users.view', 'users.roles', 'links.view']);
        $bigger = $this->role('bigger', ['admin.access', 'settings.manage']);
        $super = Role::findByName(User::SUPERADMIN_ROLE, 'web');
        $target = User::factory()->create();

        $this->actingAs($assigner);
        session($this->confirmed());

        foreach ([[$super->id], [$bigger->id], [(string) Str::uuid()], ['not-a-uuid'], [$super->id, $super->id]] as $selection) {
            Livewire::test(UsersIndex::class)
                ->call('editRoles', $target->id)
                ->set('roleSelection', $selection)
                ->call('saveRoles')
                ->assertHasErrors();
        }

        $this->assertCount(0, $target->fresh()->roles);
        $this->assertSame(0, AdminAuditLog::count());
    }

    public function test_cannot_open_role_modal_for_self_or_superadmin(): void
    {
        $assigner = $this->userWithRole('assigner', ['admin.access', 'users.view', 'users.roles']);
        $this->actingAs($assigner);
        session($this->confirmed());

        Livewire::test(UsersIndex::class)->call('editRoles', $assigner->id)->assertForbidden();
        Livewire::test(UsersIndex::class)->call('editRoles', $this->superadmin()->id)->assertForbidden();
    }

    public function test_selected_tampering_in_role_form_via_replay(): void
    {
        $manager = $this->userWithRole('role-manager', ['admin.access', 'roles.manage', 'links.view']);
        $snapshot = $this->snapshotOf($manager, '/admin/roles');

        $this->replay($manager, $snapshot, 'save', [], ['name' => 'escalate', 'selected' => ['links.view', 'roles.manage', 'settings.manage']])
            ->assertOk();
        $this->assertNull(Role::where('name', 'escalate')->first());

        // Non-string entries are dropped, not crashed on.
        $this->replay($manager, $snapshot, 'save', [], ['name' => 'nested', 'selected' => [['settings.manage'], 5, 'links.view']])
            ->assertOk();
        $this->assertEqualsCanonicalizing(['admin.access', 'links.view'], Role::findByName('nested', 'web')->permissions->pluck('name')->all());
    }

    public function test_role_id_is_locked_in_form(): void
    {
        $manager = $this->userWithRole('role-manager', ['admin.access', 'roles.manage']);
        $super = Role::findByName(User::SUPERADMIN_ROLE, 'web');
        $snapshot = $this->snapshotOf($manager, '/admin/roles');

        $this->assertFalse($this->replay($manager, $snapshot, 'save', [], ['editingId' => $super->id, 'name' => 'hijack'])->isSuccessful());
        $this->assertSame(User::SUPERADMIN_ROLE, $super->fresh()->name);
        $this->assertNull(Role::where('name', 'hijack')->first());
    }

    public function test_saving_locked_role_edit_pages_is_forbidden(): void
    {
        $manager = $this->userWithRole('role-manager', ['admin.access', 'roles.manage']);
        $bigger = $this->role('bigger', ['admin.access', 'settings.manage']);
        $own = Role::findByName('role-manager', 'web');
        $super = Role::findByName(User::SUPERADMIN_ROLE, 'web');

        $list = $this->snapshotOf($manager, '/admin/roles');

        foreach ([$super, $own, $bigger] as $role) {
            // Opening a locked role is read-only; saving it must still be refused.
            $snapshot = $this->replay($manager, $list, 'openEdit', [$role->id])->assertOk()->json('components.0.snapshot');
            $this->replay($manager, $snapshot, 'save', [], ['name' => 'renamed-'.$role->getKey(), 'selected' => ['admin.access', 'roles.manage']])
                ->assertForbidden();
        }

        $this->assertSame(['admin.access', 'roles.manage'], $own->fresh()->permissions->pluck('name')->sort()->values()->all());
        $this->assertSame('bigger', $bigger->fresh()->name);
        $this->assertTrue($bigger->fresh()->hasPermissionTo('settings.manage'));
    }

    // --- Escalation -------------------------------------------------------------

    public function test_cannot_add_foreign_permission_when_editing_editable_role(): void
    {
        $manager = $this->userWithRole('role-manager', ['admin.access', 'roles.manage', 'links.view']);
        $role = $this->role('link-viewer', ['admin.access', 'links.view']);
        session($this->confirmed());

        try {
            app(SaveRole::class)->handle($manager, $role, 'link-viewer', ['links.view', 'users.roles'], null);
            $this->fail('escalation via edit allowed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('permissions', $e->errors());
        }

        try {
            app(SaveRole::class)->handle($manager, $role, User::SUPERADMIN_ROLE, ['links.view'], null);
            $this->fail('rename to superadmin allowed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
        }

        $this->assertFalse($role->fresh()->hasPermissionTo('users.roles'));
    }

    public function test_cannot_delete_own_or_bigger_role(): void
    {
        $manager = $this->userWithRole('role-manager', ['admin.access', 'roles.manage']);
        $bigger = $this->role('bigger', ['admin.access', 'settings.manage']);
        session($this->confirmed());

        foreach ([Role::findByName('role-manager', 'web'), $bigger] as $role) {
            try {
                app(DeleteRole::class)->handle($manager, $role, null);
                $this->fail("deleted {$role->name}");
            } catch (AuthorizationException) {
                $this->assertNotNull(Role::find($role->id));
            }
        }
    }

    public function test_delete_role_in_use_and_bad_id_via_livewire(): void
    {
        $admin = $this->superadmin();
        $this->userWithRole('moderator', ['admin.access', 'links.view']);
        $this->actingAs($admin);
        session($this->confirmed());

        Livewire::test(RolesIndex::class)
            ->call('delete', Role::findByName('moderator', 'web')->id)
            ->assertHasErrors('role');
        Livewire::test(RolesIndex::class)->call('delete', 'not-a-uuid')->assertHasErrors('roleId');

        $this->assertNotNull(Role::where('name', 'moderator')->first());
    }

    public function test_assign_requires_recent_password_confirmation(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create();
        $role = $this->role('link-viewer', ['admin.access', 'links.view']);
        $this->actingAs($admin);
        session(['auth.password_confirmed_at' => time() - 1000]);

        Livewire::test(UsersIndex::class)->call('editRoles', $target->id)->assertRedirect(route('password.confirm'));

        $this->expectException(AuthorizationException::class);
        app(AssignUserRoles::class)->handle($admin, $target, [$role->id], null);
    }

    // --- Superadmin ---------------------------------------------------------------

    public function test_superadmin_can_do_everything_in_admin(): void
    {
        $admin = $this->superadmin();
        $other = $this->superadmin();
        $target = User::factory()->create();
        $editable = $this->role('editable', []);
        session($this->confirmed());

        foreach (['/admin', '/admin/users', '/admin/links', '/admin/plans', '/admin/audit-logs', '/admin/settings', '/admin/roles'] as $uri) {
            $this->actingAs($admin)->withSession($this->confirmed())->get($uri)->assertOk();
        }

        $all = app(SaveRole::class)->handle($admin, null, 'everything', PermissionName::values(), null);
        $this->assertCount(count(PermissionName::values()), $all->permissions);

        app(AssignUserRoles::class)->handle($admin, $target, [Role::findByName(User::SUPERADMIN_ROLE, 'web')->id], null);
        $this->assertTrue($target->fresh()->isSuperadmin());

        app(AssignUserRoles::class)->handle($admin, $other, [], null);
        $this->assertFalse($other->fresh()->isSuperadmin());

        app(DeleteRole::class)->handle($admin, $editable, null);
        $this->assertNull(Role::find($editable->id));
    }

    public function test_superadmin_cannot_delete_other_users_link_in_client_area(): void
    {
        $link = Link::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->actingAs($this->superadmin());

        Livewire::test(ClientLinks::class)->call('delete', $link->id)->assertForbidden();

        $this->assertNotNull(Link::find($link->id));
    }

    // --- Login redirect & cache -----------------------------------------------------

    public function test_login_without_admin_access_goes_to_dashboard(): void
    {
        foreach ([User::factory()->create(), $this->userWithRole('no-admin', ['links.view'])] as $user) {
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
            $this->post('/logout');
        }
    }

    public function test_admin_login_honours_only_admin_intended_url(): void
    {
        $user = $this->userWithRole('moderator', ['admin.access', 'links.view']);

        $this->withSession(['url.intended' => url('/dashboard/links')])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');

        $this->withSession(['url.intended' => url('/admin/links')])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(url('/admin/links'));
    }

    public function test_permission_changes_apply_without_relogin(): void
    {
        $admin = $this->superadmin();
        $role = $this->role('link-viewer', ['admin.access', 'links.view']);
        $user = User::factory()->create()->assignRole($role);
        session($this->confirmed());

        $this->actingAs($user->fresh())->get('/admin/links')->assertOk();

        app(SaveRole::class)->handle($admin, $role, 'link-viewer', ['audit.view'], null);
        $this->actingAs($user->fresh())->get('/admin/links')->assertForbidden();
        $this->actingAs($user->fresh())->get('/admin/audit-logs')->assertOk();

        app(AssignUserRoles::class)->handle($admin, $user->fresh(), [], null);
        $this->actingAs($user->fresh())->get('/admin')->assertForbidden();
    }

    // --- Audit ----------------------------------------------------------------------

    public function test_role_actions_are_audited_with_diff(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create();
        session($this->confirmed());

        $role = app(SaveRole::class)->handle($admin, null, 'auditor', ['audit.view'], '10.0.0.1');
        $created = AdminAuditLog::where('action', 'role.created')->firstOrFail();
        $this->assertSame([$admin->id, '10.0.0.1', $role->id], [$created->admin_id, $created->ip, $created->subject_id]);
        $this->assertEqualsCanonicalizing(['admin.access', 'audit.view'], $created->changes['added']);

        app(SaveRole::class)->handle($admin, $role, 'auditor-2', ['links.view'], null);
        $updated = AdminAuditLog::where('action', 'role.updated')->firstOrFail()->changes;
        $this->assertSame(['old' => 'auditor', 'new' => 'auditor-2'], $updated['name']);
        $this->assertSame(['links.view'], $updated['added']);
        $this->assertSame(['audit.view'], $updated['removed']);

        app(AssignUserRoles::class)->handle($admin, $target, [$role->id], null);
        app(AssignUserRoles::class)->handle($admin, $target->fresh(), [$role->id], null);
        app(AssignUserRoles::class)->handle($admin, $target->fresh(), [], null);
        $logs = AdminAuditLog::where('action', 'user.roles_updated')->orderBy('created_at')->get();
        $this->assertCount(2, $logs, 'no-op assignment must not be logged');
        $this->assertContains(['added' => [], 'removed' => ['auditor-2']], $logs->pluck('changes')->all());

        app(DeleteRole::class)->handle($admin, $role->fresh(), null);
        $deleted = AdminAuditLog::where('action', 'role.deleted')->firstOrFail()->changes;
        $this->assertSame('auditor-2', $deleted['name']);
        $this->assertEqualsCanonicalizing(['admin.access', 'links.view'], $deleted['permissions']);
    }

    public function test_custom_admin_role_is_not_auto_linked_by_google_email(): void
    {
        $user = $this->userWithRole('moderator', ['admin.access', 'links.view']);
        $google = (new GoogleUser)
            ->setRaw(['sub' => 'g-qa', 'email' => $user->email, 'email_verified' => true])
            ->map(['id' => 'g-qa', 'name' => 'Budi', 'email' => $user->email, 'avatar' => null]);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertNull($user->fresh()->google_id);
        $this->assertGuest();
    }

    // Typed Livewire props reject scalar/array swaps (TypeError, 500) without side effects.
    public function test_type_mismatch_tampering_has_no_side_effect(): void
    {
        $manager = $this->userWithRole('role-manager', ['admin.access', 'roles.manage', 'users.view', 'users.roles']);
        $target = User::factory()->create();

        $form = $this->snapshotOf($manager, '/admin/roles');
        $this->assertFalse($this->replay($manager, $form, 'save', [], ['name' => 'typed', 'selected' => 'settings.manage'])->isSuccessful());
        $this->assertFalse($this->replay($manager, $form, 'save', [], ['name' => ['x'], 'selected' => []])->isSuccessful());

        $opened = $this->replay($manager, $this->snapshotOf($manager, '/admin/users'), 'editRoles', [$target->id])->assertOk();
        $this->assertFalse($this->replay($manager, $opened->json('components.0.snapshot'), 'saveRoles', [], ['roleSelection' => 'x'])->isSuccessful());

        $this->assertNull(Role::where('name', 'typed')->first());
        $this->assertCount(0, $target->fresh()->roles);
        $this->assertSame(0, AdminAuditLog::count());
    }
}
