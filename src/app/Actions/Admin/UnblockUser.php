<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UnblockUser
{
    public function __construct(protected AuditLogger $audit) {}

    public function handle(User $admin, User $target, ?string $ip = null): void
    {
        Gate::forUser($admin)->authorize('unblock', $target);

        DB::transaction(function () use ($admin, $target, $ip) {
            $previous = $target->blocked_at;
            $target->forceFill(['blocked_at' => null, 'blocked_reason' => null])->save();

            $this->audit->log($admin, 'user.unblock', $target, ['blocked_at' => [(string) $previous, null]], null, $ip);
        });
    }
}
