<?php

namespace App\Enums;

// Channels this app can integrate with (Xendit Payments API v3). Which ones are offered lives in payment_methods.
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

    public function isQris(): bool
    {
        return $this === self::Qris;
    }

    // Xendit channel_code.
    public function code(): string
    {
        return $this->kind() === PaymentChannelKind::VirtualAccount ? $this->value.'_VIRTUAL_ACCOUNT' : $this->value;
    }

    // Stored in payments.payment_method.
    public function method(): string
    {
        return match ($this->kind()) {
            PaymentChannelKind::Qris => 'QRIS',
            PaymentChannelKind::VirtualAccount => 'VIRTUAL_ACCOUNT',
            PaymentChannelKind::EWallet => 'EWALLET',
        };
    }

    /**
     * actions[].descriptor values that carry what the customer pays with, in order of preference.
     *
     * @return list<string>
     */
    public function descriptors(): array
    {
        return match ($this->kind()) {
            PaymentChannelKind::Qris => ['QR_STRING'],
            PaymentChannelKind::VirtualAccount => ['VIRTUAL_ACCOUNT_NUMBER'],
            PaymentChannelKind::EWallet => ['WEB_URL', 'DEEPLINK_URL', 'QR_STRING'],
        };
    }

    // OVO confirms through a push notification to the customer's number; there is nothing to show.
    public function needsMobileNumber(): bool
    {
        return $this === self::Ovo;
    }

    // E-wallet expiry is fixed by the provider (Xendit channel docs); null = use the configured expiry.
    public function fixedExpiryMinutes(): ?int
    {
        return match ($this) {
            self::Ovo, self::AstraPay => 15,
            self::ShopeePay, self::LinkAja => 30,
            self::Dana => 1440,
            default => null,
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

    // Badge background (approximate brand color).
    public function color(): string
    {
        return match ($this) {
            self::Qris => '#15181d',
            self::Bca => '#0060af',
            self::Bni => '#f15a23',
            self::Bri => '#00529c',
            self::Mandiri => '#003d79',
            self::Permata => '#d71920',
            self::Bsi => '#00a39d',
            self::Ovo => '#4c3494',
            self::Dana => '#108ee9',
            self::ShopeePay => '#ee4d2d',
            self::LinkAja => '#e82127',
            self::AstraPay => '#0066b3',
        };
    }
}
