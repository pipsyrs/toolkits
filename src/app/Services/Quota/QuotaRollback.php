<?php

namespace App\Services\Quota;

use RuntimeException;

// Internal signal to roll back a partial reservation.
final class QuotaRollback extends RuntimeException {}
