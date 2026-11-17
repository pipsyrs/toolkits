<?php

namespace App\Actions\Admin;

use App\Enums\AdminResource;
use App\Enums\PermissionAction;
use App\Enums\SupportReportStatus;
use App\Models\SupportReport;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// CS response to a user report: status change and/or reply shown to the user. Audited.
class HandleSupportReport
{
    public const REPLY_MAX = 5000;

    public function __construct(protected AuditLogger $audit) {}

    public function handle(User $admin, string $id, string $status, string $reply, ?string $ip = null): void
    {
        Gate::forUser($admin)->authorize(AdminResource::Reports->permission(PermissionAction::Update));

        $data = Validator::make(['status' => $status, 'reply' => trim($reply)], [
            'status' => ['required', Rule::enum(SupportReportStatus::class)],
            'reply' => ['nullable', 'string', 'max:'.self::REPLY_MAX],
        ], [], ['status' => 'status', 'reply' => 'balasan'])->validate();

        $new = SupportReportStatus::from($data['status']);
        $reply = ($data['reply'] ?? '') === '' ? null : $data['reply'];

        DB::transaction(function () use ($admin, $id, $new, $reply, $ip) {
            $report = SupportReport::query()->whereKey($id)->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(['report' => 'Laporan tidak ditemukan.']);

            $old = $report->status;

            if ($old === $new && $report->admin_reply === $reply) {
                return;
            }

            $report->forceFill([
                'status' => $new,
                'admin_reply' => $reply,
                'handled_by' => $admin->getKey(),
                // Keeps the first resolution time when only the reply is edited.
                'resolved_at' => $new->isFinal() ? ($report->resolved_at ?? now()) : null,
            ])->save();

            $this->audit->log($admin, 'support_report.update', $report, array_filter([
                'status' => $old !== $new ? [$old->value, $new->value] : null,
                'reply_changed' => $report->wasChanged('admin_reply') ?: null,
            ]), null, $ip);
        });
    }
}
