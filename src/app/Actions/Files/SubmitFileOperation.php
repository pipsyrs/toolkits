<?php

namespace App\Actions\Files;

use App\Enums\FileOperationStatus;
use App\Exceptions\QuotaExceededException;
use App\Jobs\ProcessFileOperation;
use App\Models\FileOperation;
use App\Models\User;
use App\Services\Quota\QuotaService;
use App\Services\Tier\Entitlements;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\TierResolver;
use App\Services\Tools\ToolCatalog;
use Closure;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SubmitFileOperation
{
    public const METRIC = 'file_ops';

    // Tools that work on a fixed number of files.
    private const MIN_FILES = ['pdf-merge' => 2];

    private const MAX_FILES = ['pdf-split' => 1, 'remove-bg' => 1];

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        protected TierResolver $tiers,
        protected PlanLimits $plans,
        protected QuotaService $quota,
        protected ToolCatalog $catalog,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  array<string, mixed>  $options  already validated by the tool component
     *
     * @throws ValidationException
     * @throws QuotaExceededException
     */
    public function handle(?User $user, string $guestId, string $ip, string $tool, array $files, array $options): FileOperation
    {
        $tier = $this->tiers->forUser($user);
        $limits = $this->plans->for($tier);

        $this->validateFiles($tool, $files, $limits);

        // Empty = no cap on concurrent operations; a missing key keeps the safe default of 1.
        $maxActive = array_key_exists('max_active_ops', $limits) ? $limits['max_active_ops'] : 1;

        if ($maxActive !== null) {
            $this->ensureCapacity($user, $guestId, (int) $maxActive);
        }

        $subjects = $this->quota->subjectsFor($user, $tier, $guestId, $ip);

        if (! $this->quota->reserve($subjects, self::METRIC, 1, $this->quota->limitsFor($subjects, self::METRIC, $limits))) {
            throw new QuotaExceededException(metric: self::METRIC);
        }

        $retention = Entitlements::retentionMinutes($limits);
        $operation = FileOperation::create([
            'user_id' => $user?->getKey(),
            'guest_id' => $guestId,
            'tool' => $tool,
            'status' => FileOperationStatus::Pending,
            'options' => $options,
            'inputs' => [],
            'quota_subjects' => $subjects,
            // null = kept until the owner deletes it.
            'expires_at' => $retention === null ? null : now()->addMinutes($retention),
        ]);

        try {
            $operation->forceFill(['inputs' => $this->store($operation, $files)])->save();
        } catch (Throwable $e) {
            $this->quota->refund($subjects, self::METRIC, 1);
            Storage::disk(config('toolkits.files.disk'))->deleteDirectory($operation->directory());
            $operation->delete();

            throw $e;
        }

        ProcessFileOperation::dispatch($operation)->onQueue((string) ($limits['queue'] ?? 'default'));

        return $operation;
    }

    /**
     * @param  array<string, mixed>  $limits
     */
    private function validateFiles(string $tool, array $files, array $limits): void
    {
        $entry = $this->catalog->find($tool);
        $accept = array_filter(explode(',', (string) ($entry['accept'] ?? '')));

        if ($entry === null || $accept === []) {
            throw ValidationException::withMessages(['files' => 'Alat ini tidak menerima file.']);
        }

        $max = min(Entitlements::uploadLimit($limits, 'max_files_per_op'), self::MAX_FILES[$tool] ?? PHP_INT_MAX);
        $min = self::MIN_FILES[$tool] ?? 1;
        $maxKb = Entitlements::uploadLimit($limits, 'max_file_mb') * 1024;

        Validator::make(['files' => $files], [
            'files' => ['required', 'array', "min:{$min}", "max:{$max}"],
            'files.*' => ['required', 'file', "max:{$maxKb}", function (string $attribute, mixed $file, Closure $fail) use ($accept) {
                if (! in_array($this->contentType($file), $accept, true)) {
                    $fail('Tipe file tidak didukung alat ini.');
                }
            }],
        ], [
            'files.required' => 'Pilih file terlebih dahulu.',
            'files.min' => "Pilih minimal {$min} file.",
            'files.max' => "Maksimal {$max} file per proses.",
            'files.*.max' => 'Ukuran file melebihi '.($maxKb / 1024).' MB.',
            'files.*.file' => 'Upload file gagal, coba lagi.',
        ])->validate();
    }

    // Sniffed from the bytes: Livewire's getMimeType() falls back to the client extension.
    private function contentType(mixed $file): ?string
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : false;

        return $path ? ((new finfo(FILEINFO_MIME_TYPE))->file($path) ?: null) : null;
    }

    private function ensureCapacity(?User $user, string $guestId, int $maxActive): void
    {
        $active = FileOperation::query()
            ->ownedBy($user, $guestId)
            ->whereIn('status', FileOperationStatus::activeValues())
            ->unexpired()
            ->latest()
            ->pluck('tool');

        if ($active->count() >= max(1, $maxActive)) {
            $name = $this->catalog->find((string) $active->first())['name'] ?? 'alat lain';

            throw ValidationException::withMessages([
                'files' => "Masih ada proses yang berjalan di {$name}. Tunggu sampai selesai, atau buka alat itu untuk membatalkannya.",
            ]);
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{path: string, name: string, size: int}>
     */
    private function store(FileOperation $operation, array $files): array
    {
        $stored = [];

        foreach (array_values($files) as $i => $file) {
            // Server-side name; the client name is kept for display only.
            $path = $file->storeAs(
                $operation->directory(),
                sprintf('in-%03d.%s', $i + 1, self::EXTENSIONS[$this->contentType($file)] ?? 'bin'),
                config('toolkits.files.disk'),
            );

            if ($path === false) {
                throw new RuntimeException('Failed to store upload.');
            }

            $stored[] = ['path' => $path, 'name' => mb_substr($file->getClientOriginalName(), 0, 150), 'size' => (int) $file->getSize()];
        }

        return $stored;
    }
}
