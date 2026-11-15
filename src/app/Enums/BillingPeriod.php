<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Bulanan',
            self::Yearly => 'Tahunan',
        };
    }

    public function addTo(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Monthly => $date->addMonthsNoOverflow(1),
            self::Yearly => $date->addYearsNoOverflow(1),
        };
    }
}
