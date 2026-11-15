<?php

namespace App\Actions\Admin;

use App\Enums\PermissionName;
use App\Models\ToolCategory;
use App\Models\ToolSetting;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Tools\ToolCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateToolCategory
{
    public function __construct(protected AuditLogger $audit, protected ToolCatalog $catalog) {}

    public function handle(User $admin, string $slug, string $category, ?string $ip = null): void
    {
        Gate::forUser($admin)->authorize(PermissionName::ToolsManage->value);

        $tool = $this->catalog->find($slug)
            ?? throw ValidationException::withMessages(['slug' => 'Alat tidak ditemukan.']);

        if ($tool['category'] === $category) {
            return;
        }

        try {
            DB::transaction(function () use ($admin, $slug, $tool, $category, $ip) {
                // Locked so the category cannot be deleted between this check and the write.
                if (! ToolCategory::query()->where('slug', $category)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['category' => 'Kategori tidak ditemukan.']);
                }

                $setting = ToolSetting::query()->updateOrCreate(['slug' => $slug], ['category' => $category]);

                $this->audit->log($admin, 'tool.category', $setting, [
                    'slug' => $slug,
                    'from' => $tool['category'],
                    'to' => $category,
                ], null, $ip);
            });
        } finally {
            $this->catalog->forget();
        }
    }
}
