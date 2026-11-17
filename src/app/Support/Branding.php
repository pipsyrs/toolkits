<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

// Where uploaded brand images live and their public URLs; null = use the built-in logo/favicon.
class Branding
{
    public const DISK = 'public';

    public const DIRECTORY = 'branding';

    public static function logoUrl(): ?string
    {
        return self::url(config('toolkits.branding.logo'));
    }

    public static function faviconUrl(): ?string
    {
        return self::url(config('toolkits.branding.favicon'));
    }

    public static function url(mixed $path): ?string
    {
        return self::owns($path) ? Storage::disk(self::DISK)->url($path) : null;
    }

    // Only files this app wrote under DIRECTORY are served or deleted.
    public static function owns(mixed $path): bool
    {
        return is_string($path)
            && str_starts_with($path, self::DIRECTORY.'/')
            && ! str_contains($path, '..');
    }
}
