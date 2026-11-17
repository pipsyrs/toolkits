<?php

namespace Tests\Feature;

use App\Livewire\Admin\ToolCategories\Index;
use App\Livewire\Admin\Tools\Show;
use App\Models\AdminAuditLog;
use App\Models\Role;
use App\Models\ToolCategory;
use App\Models\User;
use App\Services\Tools\ToolCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ToolCategoriesTest extends TestCase
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

    private function category(string $slug): ToolCategory
    {
        return ToolCategory::query()->where('slug', $slug)->sole();
    }

    public function test_page_requires_tools_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/tool-categories')->assertForbidden();

        $viewer = User::factory()->create()->assignRole(Role::create(['name' => 'viewer', 'guard_name' => 'web'])->givePermissionTo(['admin.access', 'links.view_any']));
        $this->actingAs($viewer)->get('/admin/tool-categories')->assertForbidden();
        Livewire::actingAs($viewer)->test(Index::class)->assertForbidden();

        $this->actingAs($this->admin())->get('/admin/tool-categories')->assertOk()->assertSee('Gambar')->assertSee('PDF');
    }

    public function test_rename_changes_label_on_public_tools_page(): void
    {
        Livewire::actingAs($this->admin())->test(Index::class)->call('openEdit', $this->category('image')->id)->set('name', 'Foto')->call('save')->assertHasNoErrors();

        $this->assertSame('Foto', collect(app(ToolCatalog::class)->all())->firstWhere('slug', 'image-convert')['category_name']);
        $this->get('/tools')->assertOk()->assertSee('Foto');
        $this->assertSame(1, AdminAuditLog::where('action', 'tool_category.update')->count());
    }

    public function test_tool_can_move_to_new_category_and_used_category_cannot_be_deleted(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Index::class)->call('openCreate')->set('slug', 'teks')->set('name', 'Teks')->call('save')->assertHasNoErrors();

        // Empty category gets no filter button on the public page.
        $this->get('/tools')->assertDontSee("filter = 'teks'", false);

        Livewire::actingAs($admin)->test(Show::class, ['slug' => 'shorten'])->set('category', 'teks')->assertHasNoErrors();

        $this->assertSame('teks', app(ToolCatalog::class)->find('shorten')['category']);
        $this->assertSame(1, AdminAuditLog::where('action', 'tool.category')->count());
        $this->get('/tools')->assertSee("filter = 'teks'", false);

        Livewire::actingAs($admin)->test(Index::class)->call('delete', $this->category('teks')->id)->assertHasErrors('list');
        $this->assertNotNull($this->category('teks'));
    }

    public function test_status_override_keeps_category_override(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Show::class, ['slug' => 'shorten'])->set('category', 'pdf');
        Livewire::actingAs($admin)->test(Show::class, ['slug' => 'shorten'])->call('toggle');

        $tool = app(ToolCatalog::class)->find('shorten');
        $this->assertSame('pdf', $tool['category']);
        $this->assertNotSame('live', $tool['status']);
    }

    public function test_unknown_category_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(Show::class, ['slug' => 'shorten'])->set('category', 'nope')
            ->assertHasErrors('category')->assertSet('category', 'link');

        $this->assertSame('link', app(ToolCatalog::class)->find('shorten')['category']);
    }

    public function test_unused_category_can_be_deleted_and_slug_is_validated(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Index::class)->call('openCreate');

        $component->set('slug', 'link')->set('name', 'Link 2')->call('save')->assertHasErrors('slug');
        $component->set('slug', 'Bad_Slug')->set('name', 'X')->call('save')->assertHasErrors('slug');
        $component->set('slug', 'video')->set('name', 'Video')->call('save')->assertHasNoErrors();

        $component->call('delete', $this->category('video')->id)->assertHasNoErrors();
        $this->assertSame(0, ToolCategory::where('slug', 'video')->count());
    }
}
