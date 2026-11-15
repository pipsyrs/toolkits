<?php

namespace App\Enums;

enum SupportReportCategory: string
{
    case Account = 'account';
    case Billing = 'billing';
    case Tool = 'tool';
    case Link = 'link';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'Akun & login',
            self::Billing => 'Pembayaran & langganan',
            self::Tool => 'Alat / error',
            self::Link => 'Link pendek',
            self::Other => 'Lainnya',
        };
    }
}
