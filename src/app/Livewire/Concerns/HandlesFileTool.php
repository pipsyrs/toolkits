<?php

namespace App\Livewire\Concerns;

use App\Actions\Files\CancelFileOperation;
use App\Actions\Files\SubmitFileOperation;
use App\Enums\FileOperationStatus;
use App\Exceptions\QuotaExceededException;
use App\Models\FileOperation;
use App\Services\Files\ResultPreview;
use App\Services\Tools\ToolCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

// Upload → queued operation → poll → download.
trait HandlesFileTool
{
    use RequiresActiveTool, ResolvesVisitor, WithFileUploads;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    #[Locked]
    public ?string $operationId = null;

    // Ad gate fires once per finished operation.
    #[Locked]
    public bool $announced = false;

    // Result finished this recently is shown again after a reload.
    private const RESTORE_DONE_MINUTES = 10;

    /**
     * @return array<string, mixed>
     */
    abstract protected function optionRules(): array;

    // Reopening the page shows the visitor's running (or just finished) operation.
    public function mountHandlesFileTool(): void
    {
        $operation = FileOperation::query()
            ->ownedBy($this->visitor(), $this->guestId())
            ->where('tool', $this->toolSlug())
            ->unexpired()
            ->where(fn ($query) => $query
                ->whereIn('status', FileOperationStatus::activeValues())
                ->orWhere(fn ($query) => $query
                    ->where('status', FileOperationStatus::Done->value)
                    ->where('finished_at', '>=', now()->subMinutes(self::RESTORE_DONE_MINUTES))))
            ->latest()
            ->first();

        if ($operation !== null) {
            $this->operationId = $operation->getKey();
            // No ad gate for a result the visitor already waited for.
            $this->announced = $operation->status === FileOperationStatus::Done;
        }
    }

    public function process(SubmitFileOperation $action): void
    {
        $this->resetErrorBag();

        if ($this->optionRules() !== []) {
            $this->validate($this->optionRules());
        }

        $files = array_values(array_filter($this->files, fn ($file) => $file instanceof UploadedFile));

        try {
            $operation = $action->handle(
                $this->visitor(), $this->guestId(), $this->clientIp(), $this->toolSlug(), $files,
                $this->only(array_keys($this->optionRules())),
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['files' => collect($e->errors())->flatten()->unique()->values()->all()]);
        } catch (QuotaExceededException $e) {
            $this->addError('quota', $e->getMessage());

            return;
        }

        // Copied to the operation folder; drop Livewire's temporary copies now.
        foreach ($files as $file) {
            $file instanceof TemporaryUploadedFile && $file->delete();
        }

        $this->files = [];
        $this->operationId = $operation->getKey();
        $this->announced = false;
        unset($this->operation);
        $this->dispatch('files-submitted');
    }

    // Polled while the operation is queued or running.
    public function checkOperation(): void
    {
        unset($this->operation);

        if ($this->operation?->isDownloadable() && ! $this->announced) {
            $this->announced = true;
            $this->dispatch('operation-done');
        }
    }

    public function cancelOperation(CancelFileOperation $action): void
    {
        $operation = $this->operation;

        if ($operation === null) {
            return;
        }

        if (! $action->handle($operation)) {
            $this->addError('files', 'Proses sudah berjalan dan tidak bisa dibatalkan lagi.');
            unset($this->operation);

            return;
        }

        $this->startOver();
    }

    public function startOver(): void
    {
        $this->resetErrorBag();
        $this->reset('files', 'operationId', 'announced');
        unset($this->operation);
    }

    #[Computed]
    public function operation(): ?FileOperation
    {
        return $this->operationId === null ? null : FileOperation::query()
            ->ownedBy($this->visitor(), $this->guestId())
            ->find($this->operationId);
    }

    /**
     * @return list<array{index: int, label: string}>
     */
    #[Computed]
    public function previews(): array
    {
        return $this->operation === null ? [] : app(ResultPreview::class)->items($this->operation);
    }

    #[Computed]
    public function accept(): string
    {
        $tool = app(ToolCatalog::class)->find($this->toolSlug());

        return (string) ($tool['accept'] ?? '');
    }
}
