<?php

namespace App\Enums;

enum QrFrame: string
{
    case None = 'none';
    case Simple = 'simple';
    case Banner = 'banner';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tanpa bingkai',
            self::Simple => 'Sederhana',
            self::Banner => 'Banner',
            self::Card => 'Kartu',
        };
    }

    public function isPro(): bool
    {
        return in_array($this, [self::Banner, self::Card], true);
    }
}
