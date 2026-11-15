<?php

namespace App\Rules;

use App\Models\Link;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidAlias implements ValidationRule
{
    public const PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail('Alias 3–30 karakter: huruf kecil, angka, dan tanda hubung (tidak di awal/akhir).');

            return;
        }

        if (in_array($value, config('toolkits.reserved_aliases', []), true)) {
            $fail('Alias ini tidak tersedia.');

            return;
        }

        if (Link::query()->where('code', $value)->exists()) {
            $fail('Alias sudah dipakai.');
        }
    }
}
