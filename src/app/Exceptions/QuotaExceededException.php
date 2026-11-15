<?php

namespace App\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(
        string $message = 'Kuota harian habis. Coba lagi besok atau upgrade ke Pro.',
        public readonly string $metric = '',
    ) {
        parent::__construct($message);
    }
}
