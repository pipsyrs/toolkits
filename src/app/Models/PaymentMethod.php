<?php

namespace App\Models;

use App\Enums\PaymentChannel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Payment method offered in checkout; channel comes from the PaymentChannel catalog.
class PaymentMethod extends Model
{
    use HasUuids;

    protected $fillable = [
        'channel',
        'name',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
