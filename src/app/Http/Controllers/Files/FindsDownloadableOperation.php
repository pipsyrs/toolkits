<?php

namespace App\Http\Controllers\Files;

use App\Http\Middleware\EnsureGuestId;
use App\Models\FileOperation;
use App\Models\User;
use Illuminate\Http\Request;

trait FindsDownloadableOperation
{
    protected function downloadableOperation(Request $request, string $operation): FileOperation
    {
        $user = $request->user();

        // 404 for other owners too, so ids cannot be probed.
        $model = FileOperation::query()
            ->ownedBy($user instanceof User ? $user : null, EnsureGuestId::idFrom($request))
            ->findOrFail($operation);

        abort_unless($model->isDownloadable(), 410, 'Hasil sudah kedaluwarsa.');

        return $model;
    }
}
