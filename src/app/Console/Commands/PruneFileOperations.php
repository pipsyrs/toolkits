<?php

namespace App\Console\Commands;

use App\Enums\FileOperationStatus;
use App\Models\FileOperation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneFileOperations extends Command
{
    protected $signature = 'files:prune';

    protected $description = 'Hapus file hasil/upload yang sudah lewat masa simpan';

    public function handle(): int
    {
        $disk = Storage::disk(config('toolkits.files.disk'));
        $count = 0;

        FileOperation::query()
            ->where('expires_at', '<=', now())
            ->where('status', '!=', FileOperationStatus::Expired->value)
            ->chunkById(200, function ($operations) use ($disk, &$count) {
                foreach ($operations as $operation) {
                    $disk->deleteDirectory($operation->directory());
                    $operation->forceFill(['status' => FileOperationStatus::Expired, 'output_path' => null])->save();
                    $count++;
                }
            });

        $this->info("{$count} operasi dibersihkan.");

        return self::SUCCESS;
    }
}
