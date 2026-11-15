<?php

namespace App\Jobs;

use App\Actions\Files\SubmitFileOperation;
use App\Enums\FileOperationStatus;
use App\Exceptions\FileProcessingException;
use App\Models\FileOperation;
use App\Services\Files\FileProcessor;
use App\Services\Quota\QuotaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessFileOperation implements ShouldQueue
{
    use Queueable;

    // Not retried: a failed conversion fails the same way again.
    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public FileOperation $operation) {}

    public function handle(FileProcessor $processor): void
    {
        $operation = $this->operation;

        // Atomic claim: a cancelled or already claimed operation is skipped.
        $claimed = FileOperation::query()->whereKey($operation->getKey())
            ->where('status', FileOperationStatus::Pending->value)
            ->update(['status' => FileOperationStatus::Processing->value, 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $operation->status = FileOperationStatus::Processing;

        try {
            $result = $processor->run($operation);

            $operation->forceFill([
                'status' => FileOperationStatus::Done,
                'output_path' => $result['path'],
                'output_name' => $result['name'],
                'output_size' => $result['size'],
                'finished_at' => now(),
            ])->save();
        } catch (FileProcessingException $e) {
            $this->markFailed($e->getMessage());
        } catch (Throwable $e) {
            Log::error('File operation failed', ['operation' => $operation->getKey(), 'tool' => $operation->tool, 'exception' => $e]);
            $this->markFailed('Terjadi kesalahan saat memproses file.');
        } finally {
            $this->deleteInputs();
        }
    }

    // Worker timeout or crash.
    public function failed(?Throwable $e): void
    {
        $this->operation->refresh();

        if ($this->operation->status->isActive()) {
            $this->markFailed('Proses melebihi batas waktu.');
        }

        $this->deleteInputs();
    }

    private function markFailed(string $message): void
    {
        $this->operation->forceFill([
            'status' => FileOperationStatus::Failed,
            'error' => $message,
            'finished_at' => now(),
        ])->save();

        // Failed operations do not count against the daily quota.
        app(QuotaService::class)->refund((array) $this->operation->quota_subjects, SubmitFileOperation::METRIC, 1);
    }

    private function deleteInputs(): void
    {
        Storage::disk(config('toolkits.files.disk'))->delete(array_column((array) $this->operation->inputs, 'path'));
    }
}
