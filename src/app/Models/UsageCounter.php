<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UsageCounter extends Model
{
    use HasUuids;

    const CREATED_AT = null;

    protected $fillable = [
        'subject',
        'metric',
        'period_date',
        'used',
    ];

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'used' => 'integer',
        ];
    }
}
