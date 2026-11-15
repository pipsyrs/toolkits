<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    // $admin null = system/CLI action.
    public function log(?User $admin, string $action, Model $subject, array $changes = [], ?string $reason = null, ?string $ip = null): AdminAuditLog
    {
        return AdminAuditLog::create([
            'admin_id' => $admin?->getKey(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'changes' => $changes ?: null,
            'reason' => $reason,
            'ip' => $ip,
        ]);
    }
}
