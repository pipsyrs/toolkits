<?php

namespace App\Exceptions;

use RuntimeException;

// Message is safe to show to the user.
class BillingException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self('Pembayaran sedang tidak tersedia. Coba lagi nanti.');
    }

    public static function channelUnavailable(): self
    {
        return new self('Metode ini sedang tidak tersedia, pilih metode lain.');
    }
}
