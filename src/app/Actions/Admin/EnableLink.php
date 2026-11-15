<?php

namespace App\Actions\Admin;

use App\Models\Link;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EnableLink
{
    public function __construct(protected AuditLogger $audit) {}

    public function handle(User $admin, Link $link, ?string $ip = null): void
    {
        Gate::forUser($admin)->authorize('enable', $link);

        DB::transaction(function () use ($admin, $link, $ip) {
            $previousReason = $link->disabled_reason;
            $link->forceFill(['disabled_at' => null, 'disabled_reason' => null])->save();

            $this->audit->log($admin, 'link.enable', $link, ['code' => $link->code, 'disabled_reason' => [$previousReason, null]], null, $ip);
        });
    }
}
