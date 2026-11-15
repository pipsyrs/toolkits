<?php

namespace App\Enums;

enum SupportReportStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Baru',
            self::InProgress => 'Diproses',
            self::Resolved => 'Selesai',
            self::Closed => 'Ditutup',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Resolved || $this === self::Closed;
    }

    // Badge tone, shared by the client and admin lists.
    public function badge(): string
    {
        return match ($this) {
            self::Open => 'bg-warn/10 text-warn',
            self::InProgress => 'bg-tool/15 text-fg',
            self::Resolved => 'bg-ok/10 text-ok',
            self::Closed => 'bg-fg/6 text-muted',
        };
    }
}
