<?php

namespace App\Http\Controllers\Files;

use App\Http\Controllers\Controller;
use App\Services\Files\ResultPreview;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PreviewFileOperationController extends Controller
{
    use FindsDownloadableOperation;

    public function __invoke(Request $request, ResultPreview $preview, string $operation, int $entry = 0): StreamedResponse
    {
        return $preview->response($this->downloadableOperation($request, $operation), $entry) ?? abort(404);
    }
}
