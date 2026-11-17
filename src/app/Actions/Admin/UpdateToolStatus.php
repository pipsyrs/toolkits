<?php

namespace App\Actions\Admin;

use App\Enums\AdminResource;
use App\Enums\PermissionAction;
use App\Enums\ToolStatus;
use App\Models\ToolSetting;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Tools\ToolCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateToolStatus
{
    public function __construct(protected AuditLogger $audit, protected ToolCatalog $catalog) {}

    public function handle(User $admin, string $slug, string $status, ?string $ip = null): void
    {
        Gate::forUser($admin)->authorize(AdminResource::Tools->permission(PermissionAction::Update));

        $current = $this->catalog->status($slug);

        if ($current === null) {
            throw ValidationException::withMessages(['slug' => 'Alat tidak ditemukan.']);
        }

        $new = ToolStatus::tryFrom($status)
            ?? throw ValidationException::withMessages(['status' => 'Status tidak valid.']);

        if ($new === $current) {
            return;
        }

        DB::transaction(function () use ($admin, $slug, $current, $new, $ip) {
            $setting = ToolSetting::query()->updateOrCreate(['slug' => $slug], ['status' => $new]);

            $this->audit->log($admin, 'tool.status', $setting, [
                'slug' => $slug,
                'from' => $current->value,
                'to' => $new->value,
            ], null, $ip);
        });

        $this->catalog->forget();
    }
}
