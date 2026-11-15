<?php

namespace App\Enums;

enum ToolStatus: string
{
    case Live = 'live';
    case Soon = 'soon';
    case Maintenance = 'maintenance';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Live => 'Aktif',
            self::Soon => 'Segera hadir',
            self::Maintenance => 'Maintenance',
            self::Disabled => 'Tidak tersedia',
        };
    }

    // Shown to visitors when the tool is not live.
    public function message(): string
    {
        return match ($this) {
            self::Live => '',
            self::Soon => 'Alat ini sedang disiapkan dan akan segera tersedia.',
            self::Maintenance => 'Alat ini sedang dalam perbaikan. Silakan coba lagi nanti.',
            self::Disabled => 'Alat ini untuk sementara tidak tersedia.',
        };
    }

    /**
     * Reasons selectable when a tool is turned off.
     *
     * @return list<self>
     */
    public static function inactiveReasons(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => $status !== self::Live));
    }
}
