<?php

namespace App\Enums;

enum QrTemplate: string
{
    case Classic = 'classic';
    case Navy = 'navy';
    case Dots = 'dots';
    case Duotone = 'duotone';
    case Soft = 'soft';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Klasik',
            self::Navy => 'Navy',
            self::Dots => 'Titik',
            self::Duotone => 'Duotone',
            self::Soft => 'Lembut',
        };
    }

    public function isPro(): bool
    {
        return in_array($this, [self::Dots, self::Duotone, self::Soft], true);
    }

    // Data modules drawn as dots; finder patterns stay square so scanners lock on.
    public function rounded(): bool
    {
        return $this->isPro();
    }

    /**
     * Default palette; every pair keeps dark-on-light contrast well above the scan minimum.
     *
     * @return array{dark: string, eye: string, bg: string, frame: string}
     */
    public function colors(): array
    {
        return match ($this) {
            self::Classic => ['dark' => '#000000', 'eye' => '#000000', 'bg' => '#ffffff', 'frame' => '#15181d'],
            self::Navy => ['dark' => '#1b2a4a', 'eye' => '#1b2a4a', 'bg' => '#ffffff', 'frame' => '#1b2a4a'],
            self::Dots => ['dark' => '#15181d', 'eye' => '#15181d', 'bg' => '#ffffff', 'frame' => '#ffc629'],
            self::Duotone => ['dark' => '#15181d', 'eye' => '#2f5bd0', 'bg' => '#ffffff', 'frame' => '#2f5bd0'],
            self::Soft => ['dark' => '#6b1f2e', 'eye' => '#6b1f2e', 'bg' => '#fbf6ec', 'frame' => '#6b1f2e'],
        };
    }
}
