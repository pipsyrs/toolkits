<?php

namespace App\Enums;

enum FileOperationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu antrean',
            self::Processing => 'Memproses',
            self::Done => 'Selesai',
            self::Failed => 'Gagal',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Pending->value, self::Processing->value];
    }
}
