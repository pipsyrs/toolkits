<?php

namespace App\Actions\Admin;

use App\Models\Link;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class DisableLink
{
    public function __construct(protected AuditLogger $audit) {}

    public function handle(User $admin, Link $link, string $reason, ?string $ip = null): void
    {
        Gate::forUser($admin)->authorize('disable', $link);

        $reason = Validator::make(['reason' => trim($reason)], [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['reason' => 'alasan'])->validate()['reason'];

        DB::transaction(function () use ($admin, $link, $reason, $ip) {
            $link->forceFill(['disabled_at' => now(), 'disabled_reason' => $reason])->save();

            $this->audit->log($admin, 'link.disable', $link, ['code' => $link->code, 'target_host' => $link->target_host], $reason, $ip);
        });
    }
}
