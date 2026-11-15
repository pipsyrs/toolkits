<?php

namespace App\Enums;

// How a payment channel is paid; decides the gateway flow, so it cannot change once a channel exists.
enum PaymentChannelKind: string
{
    case Qris = 'qris';
    case VirtualAccount = 'va';
    case EWallet = 'ewallet';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            self::VirtualAccount => 'Virtual Account',
            self::EWallet => 'E-wallet',
            self::Card => 'Kartu kredit/debit',
        };
    }

    // Stored in payments.payment_method.
    public function method(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            self::VirtualAccount => 'VIRTUAL_ACCOUNT',
            self::EWallet => 'EWALLET',
            self::Card => 'CARDS',
        };
    }

    /**
     * actions[].descriptor values that carry what the customer pays with, in order of preference.
     *
     * @return list<string>
     */
    public function descriptors(): array
    {
        return match ($this) {
            self::Qris => ['QR_STRING'],
            self::VirtualAccount => ['VIRTUAL_ACCOUNT_NUMBER'],
            self::EWallet => ['WEB_URL', 'DEEPLINK_URL', 'QR_STRING'],
            self::Card => [],
        };
    }

    // Card numbers are entered on the gateway's hosted page (payment session), never on this site.
    public function usesHostedPage(): bool
    {
        return $this === self::Card;
    }
}
