<?php

namespace App\Enums;

// Channels shipped before payment_channels existed. Only the migrations that already ran read this;
// at runtime channels come from the payment_channels table (App\Models\PaymentChannel).
enum PaymentChannel: string
{
    case Qris = 'QRIS';
    case Bca = 'BCA';
    case Bni = 'BNI';
    case Bri = 'BRI';
    case Mandiri = 'MANDIRI';
    case Permata = 'PERMATA';
    case Bsi = 'BSI';
    case Ovo = 'OVO';
    case Dana = 'DANA';
    case ShopeePay = 'SHOPEEPAY';
    case LinkAja = 'LINKAJA';
    case AstraPay = 'ASTRAPAY';

    public function kind(): PaymentChannelKind
    {
        return match ($this) {
            self::Qris => PaymentChannelKind::Qris,
            self::Ovo, self::Dana, self::ShopeePay, self::LinkAja, self::AstraPay => PaymentChannelKind::EWallet,
            default => PaymentChannelKind::VirtualAccount,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Mandiri => 'Mandiri',
            self::Permata => 'Permata',
            self::Dana => 'DANA',
            self::ShopeePay => 'ShopeePay',
            self::LinkAja => 'LinkAja',
            self::AstraPay => 'AstraPay',
            default => $this->value,
        };
    }

    public function fullLabel(): string
    {
        return $this->kind() === PaymentChannelKind::VirtualAccount ? $this->label().' Virtual Account' : $this->label();
    }
}
