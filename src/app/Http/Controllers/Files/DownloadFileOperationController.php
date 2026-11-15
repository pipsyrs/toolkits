<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadFileOperationController extends Controller
{
    use FindsDownloadableOperation;

    public function __invoke(Request $request, string $operation): StreamedResponse
    {
        $model = $this->downloadableOperation($request, $operation);

        return Storage::disk(config('toolkits.files.disk'))->download($model->output_path, $model->output_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
