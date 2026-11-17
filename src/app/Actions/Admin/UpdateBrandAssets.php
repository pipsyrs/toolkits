<?php

namespace App\Actions\Admin;

use App\Exceptions\FileProcessingException;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Files\ImageProcessor;
use App\Services\Settings\SettingsRepository;
use App\Support\Branding;
use App\Support\PasswordConfirmation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

// Logo and favicon: validated, re-encoded under a random name, audited; replaced files are removed after commit.
class UpdateBrandAssets
{
    // PNG/JPEG/WebP only: SVG can carry script, ICO cannot be re-encoded by GD.
    public const MIMES = ['png', 'jpg', 'jpeg', 'webp'];

    /** @var array<string, array{max_kb: int, side: int, format: string}> setting key => upload limit, stored longest side, stored format */
    public const ASSETS = [
        'branding.logo' => ['max_kb' => 1024, 'side' => 512, 'format' => 'webp'],
        // PNG: favicons in WebP are not supported by every browser.
        'branding.favicon' => ['max_kb' => 512, 'side' => 180, 'format' => 'png'],
    ];

    public function __construct(
        protected AuditLogger $audit,
        protected SettingsRepository $settings,
        protected ImageProcessor $images,
    ) {}

    /**
     * @param  array<string, mixed>  $uploads  setting key => new file; other values are ignored
     * @param  list<string>  $remove  setting keys to reset to the built-in default (an upload for the same key wins)
     * @return array<string, array{old: ?string, new: ?string}> audit changes
     *
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $actor, array $uploads, array $remove, ?string $ip = null): array
    {
        Gate::forUser($actor)->authorize('settings.update');

        if (! PasswordConfirmation::recent()) {
            throw new AuthorizationException('Konfirmasi password diperlukan.');
        }

        $uploads = $this->validateUploads($uploads);
        $remove = array_diff(array_intersect($remove, array_keys(self::ASSETS)), array_keys($uploads));

        $stored = [];

        try {
            foreach ($uploads as $key => $file) {
                $stored[$key] = $this->store($key, $file);
            }

            $changes = $this->persist($actor, $stored + array_fill_keys($remove, null), $ip);
        } catch (Throwable $e) {
            $this->delete($stored);

            throw $e;
        }

        $this->delete(array_column($changes, 'old'));

        if ($changes !== []) {
            $this->settings->forget();
        }

        return $changes;
    }

    /**
     * Callable before other settings are saved, so an invalid file stops the whole save.
     *
     * @param  array<string, mixed>  $uploads  setting key => new file
     * @return array<string, UploadedFile> known keys with an uploaded file
     *
     * @throws ValidationException
     */
    public function validateUploads(array $uploads): array
    {
        $uploads = array_filter(
            array_intersect_key($uploads, self::ASSETS),
            fn (mixed $file) => $file instanceof UploadedFile,
        );
        $data = [];
        $rules = [];
        $attributes = [];

        foreach ($uploads as $key => $file) {
            data_set($data, $key, $file);
            $rules[$key] = ['required', 'file', 'image', 'mimes:'.implode(',', self::MIMES), 'max:'.self::ASSETS[$key]['max_kb']];
            $attributes[$key] = Str::afterLast($key, '.');
        }

        Validator::make($data, $rules, [], $attributes)->validate();

        return $uploads;
    }

    protected function store(string $key, UploadedFile $file): string
    {
        ['side' => $side, 'format' => $format] = self::ASSETS[$key];
        $tmp = tempnam(sys_get_temp_dir(), 'brand');

        try {
            $this->images->logo($file->getRealPath(), $tmp, $side, $format);
            $path = Branding::DIRECTORY.'/'.Str::uuid7().'.'.$format;

            if (! Storage::disk(Branding::DISK)->put($path, (string) file_get_contents($tmp))) {
                throw ValidationException::withMessages([$key => 'Gambar gagal disimpan. Coba lagi.']);
            }

            return $path;
        } catch (FileProcessingException $e) {
            throw ValidationException::withMessages([$key => $e->getMessage()]);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @param  array<string, ?string>  $writes  key => new path (null = remove)
     * @return array<string, array{old: ?string, new: ?string}>
     */
    protected function persist(User $actor, array $writes, ?string $ip): array
    {
        if ($writes === []) {
            return [];
        }

        return DB::transaction(function () use ($actor, $writes, $ip) {
            $rows = AppSetting::query()->whereIn('key', array_keys($writes))->lockForUpdate()->get()->keyBy('key');
            $changes = [];
            $subject = null;

            foreach ($writes as $key => $new) {
                $row = $rows->get($key);
                $old = $this->readPath($row);

                if ($row === null && $new === null) {
                    continue;
                }

                if ($new === null) {
                    $row->delete();
                    $subject ??= $row;
                } else {
                    // Replace instead of update: also covers a row left undecryptable by an APP_KEY change.
                    $row?->delete();
                    $saved = AppSetting::create(['key' => $key, 'value' => $new]);
                    $subject ??= $saved;
                }

                $changes[$key] = ['old' => $old, 'new' => $new];
            }

            if ($subject !== null) {
                $this->audit->log($actor, 'settings.updated', $subject, $changes, null, $ip);
            }

            return $changes;
        });
    }

    protected function readPath(?AppSetting $row): ?string
    {
        try {
            $path = $row?->value;
        } catch (DecryptException) {
            return null;
        }

        return Branding::owns($path) ? $path : null;
    }

    /**
     * @param  array<array-key, ?string>  $paths
     */
    protected function delete(array $paths): void
    {
        $paths = array_values(array_filter($paths, Branding::owns(...)));

        if ($paths !== []) {
            Storage::disk(Branding::DISK)->delete($paths);
        }
    }
}
