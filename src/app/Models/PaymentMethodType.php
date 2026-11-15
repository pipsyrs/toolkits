<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Admin-managed grouping of payment methods.
class PaymentMethodType extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    public function methods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }
}
