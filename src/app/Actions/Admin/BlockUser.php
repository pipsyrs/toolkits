<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BlockUser
{
    public function __construct(protected AuditLogger $audit) {}

    public function handle(User $admin, User $target, string $reason, ?string $ip = null): void
    {
        Gate::forUser($admin)->authorize('block', $target);

        $reason = Validator::make(['reason' => trim($reason)], [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['reason' => 'alasan'])->validate()['reason'];

        DB::transaction(function () use ($admin, $target, $reason, $ip) {
            // Rotating remember_token invalidates "remember me" cookies.
            $target->forceFill([
                'blocked_at' => now(),
                'blocked_reason' => $reason,
                'remember_token' => Str::random(60),
            ])->save();

            // Kick active sessions.
            DB::table('sessions')->where('user_id', $target->getKey())->delete();

            $this->audit->log($admin, 'user.block', $target, ['blocked_at' => [null, (string) $target->blocked_at]], $reason, $ip);
        });
    }
}
