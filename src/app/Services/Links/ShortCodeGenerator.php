<?php

namespace App\Services\Links;

use App\Models\Link;
use RuntimeException;

class ShortCodeGenerator
{
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    private const LENGTH = 7;

    private const MAX_ATTEMPTS = 5;

    public function generate(): string
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $code = $this->random();

            if (! in_array($code, config('toolkits.reserved_aliases', []), true)
                && ! Link::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Gagal membuat kode unik, coba lagi.');
    }

    private function random(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }
}
