<?php

namespace App\Models;

use App\Enums\PaymentChannelKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Master data of gateway channels; kind decides the payment flow, code is what methods and payments store.
class PaymentChannel extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'gateway_code',
        'name',
        'kind',
        'color',
        'needs_mobile_number',
        'fixed_expiry_minutes',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PaymentChannelKind::class,
            'needs_mobile_number' => 'boolean',
            'fixed_expiry_minutes' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function methods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'channel', 'code');
    }

    public function fullLabel(): string
    {
        return $this->kind === PaymentChannelKind::VirtualAccount ? $this->name.' Virtual Account' : $this->name;
    }
}
