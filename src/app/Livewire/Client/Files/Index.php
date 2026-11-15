<?php

namespace App\Livewire\Client\Files;

use App\Actions\Files\DeleteFileOperation;
use App\Models\FileOperation;
use App\Services\Tools\ToolCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::client')]
#[Title('File')]
class Index extends Component
{
    use WithPagination;

    private const PER_PAGE = 20;

    public function delete(string $operationId, DeleteFileOperation $action): void
    {
        Validator::make(['operationId' => $operationId], ['operationId' => ['required', 'uuid']])->validate();

        // Scoped to the owner: another user's id is a 404.
        $operation = FileOperation::query()->where('user_id', auth()->id())->findOrFail($operationId);

        if (! $action->handle($operation)) {
            $this->addError('files', 'File masih diproses. Hapus setelah prosesnya selesai.');
        }
    }

    public function render(ToolCatalog $catalog): View
    {
        $operations = FileOperation::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(self::PER_PAGE, ['id', 'tool', 'status', 'inputs', 'output_path', 'output_name', 'output_size', 'error', 'expires_at', 'created_at']);

        return view('livewire.client.files.index', [
            'operations' => $operations,
            'tools' => collect($catalog->all())->keyBy('slug'),
            'polling' => $operations->contains(fn (FileOperation $operation) => $operation->status->isActive()),
        ]);
    }
}
