<?php

namespace App\Actions\Files;

use App\Models\FileOperation;
use Illuminate\Support\Facades\Storage;

class DeleteFileOperation
{
    // Running operations are left alone; the worker still writes into their folder.
    public function handle(FileOperation $operation): bool
    {
        if ($operation->status->isActive()) {
            return false;
        }

        Storage::disk(config('toolkits.files.disk'))->deleteDirectory($operation->directory());
        $operation->delete();

        return true;
    }
}
