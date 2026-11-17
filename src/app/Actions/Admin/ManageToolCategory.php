<?php

namespace App\Actions\Admin;

use App\Actions\Admin\Concerns\SwapsSortOrder;
use App\Enums\AdminResource;
use App\Enums\PermissionAction;
use App\Models\ToolCategory;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Tools\ToolCatalog;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// Master data of tool categories: authorized, audited, and invalidates the tool catalog.
class ManageToolCategory
{
    use SwapsSortOrder;

    public const SLUG_MAX = 30;

    public const NAME_MAX = 40;

    public function __construct(protected AuditLogger $audit, protected ToolCatalog $catalog) {}

    public function create(User $admin, string $slug, string $name, ?string $ip = null): ToolCategory
    {
        $data = Validator::make(['slug' => strtolower(trim($slug)), 'name' => trim($name)], [
            'slug' => ['required', 'string', 'max:'.self::SLUG_MAX, 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => $this->nameRules(),
        ], ['slug.regex' => 'Slug hanya huruf kecil, angka, dan tanda -.'], ['slug' => 'slug', 'name' => 'nama'])->validate();

        return $this->change($admin, PermissionAction::Create, function () use ($admin, $data, $ip) {
            if (ToolCategory::query()->where('slug', $data['slug'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['slug' => 'Slug kategori sudah dipakai.']);
            }

            try {
                $category = ToolCategory::create($data + ['sort' => (int) ToolCategory::query()->max('sort') + 1]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['slug' => 'Slug kategori sudah dipakai.']);
            }

            $this->audit->log($admin, 'tool_category.create', $category, $data, null, $ip);

            return $category;
        });
    }

    public function update(User $admin, string $id, string $name, ?string $ip = null): void
    {
        $name = trim($name);

        Validator::make(['name' => $name], ['name' => $this->nameRules()], [], ['name' => 'nama'])->validate();

        $this->change($admin, PermissionAction::Update, function () use ($admin, $id, $name, $ip) {
            $category = $this->locked($id);

            if ($category->name === $name) {
                return;
            }

            $this->audit->log($admin, 'tool_category.update', $category, ['name' => [$category->name, $name]], null, $ip);
            $category->update(['name' => $name]);
        });
    }

    public function move(User $admin, string $id, bool $up, ?string $ip = null): void
    {
        $this->change($admin, PermissionAction::Update, function () use ($admin, $id, $up, $ip) {
            $category = $this->locked($id);
            $all = ToolCategory::query()->orderBy('sort')->orderBy('id')->lockForUpdate()->get();

            if ($this->swapWithNeighbour($all, $category, $up)) {
                $this->audit->log($admin, 'tool_category.move', $category, ['slug' => $category->slug, 'direction' => $up ? 'up' : 'down'], null, $ip);
            }
        });
    }

    // Refused while any tool (config default or override) is in the category.
    public function delete(User $admin, string $id, ?string $ip = null): void
    {
        $this->change($admin, PermissionAction::Delete, function () use ($admin, $id, $ip) {
            $category = $this->locked($id);
            // Fresh read after the lock: an assignment that just committed may still be cached as absent.
            $this->catalog->forget();
            $used = $this->usage($category->slug);

            if ($used > 0) {
                throw ValidationException::withMessages(['category' => "Kategori masih dipakai {$used} alat. Pindahkan alatnya dulu."]);
            }

            $this->audit->log($admin, 'tool_category.delete', $category, ['slug' => $category->slug, 'name' => $category->name], null, $ip);
            $category->delete();
        });
    }

    private function usage(string $slug): int
    {
        return collect($this->catalog->all())->where('category', $slug)->count();
    }

    /**
     * @return list<string>
     */
    private function nameRules(): array
    {
        return ['required', 'string', 'max:'.self::NAME_MAX, 'regex:/^[^\p{C}]+$/u'];
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    private function change(User $admin, PermissionAction $action, Closure $change): mixed
    {
        Gate::forUser($admin)->authorize(AdminResource::ToolCategories->permission($action));

        try {
            return DB::transaction($change);
        } finally {
            $this->catalog->forget();
        }
    }

    private function locked(string $id): ToolCategory
    {
        return ToolCategory::query()->whereKey($id)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['category' => 'Kategori tidak ditemukan.']);
    }
}
