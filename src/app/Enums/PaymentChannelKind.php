<?php

namespace App\Enums;

enum PaymentChannelKind: string
{
    case Qris = 'qris';
    case VirtualAccount = 'va';
    case EWallet = 'ewallet';

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            self::VirtualAccount => 'Virtual Account',
            self::EWallet => 'E-wallet',
        };
    }
}
