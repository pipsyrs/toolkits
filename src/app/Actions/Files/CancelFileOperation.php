<?php

namespace App\Actions\Files;

use App\Enums\FileOperationStatus;
use App\Models\FileOperation;
use App\Services\Quota\QuotaService;
use Illuminate\Support\Facades\Storage;

class CancelFileOperation
{
    public function __construct(protected QuotaService $quota) {}

    // Only queued operations; returns false when the worker already took it.
    public function handle(FileOperation $operation): bool
    {
        $cancelled = FileOperation::query()->whereKey($operation->getKey())
            ->where('status', FileOperationStatus::Pending->value)
            ->update([
                'status' => FileOperationStatus::Cancelled->value,
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

        if ($cancelled !== 1) {
            return false;
        }

        $this->quota->refund((array) $operation->quota_subjects, SubmitFileOperation::METRIC, 1);
        Storage::disk(config('toolkits.files.disk'))->deleteDirectory($operation->directory());

        return true;
    }
}
