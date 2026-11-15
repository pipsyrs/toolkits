<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug',
        'name',
        'limits',
        'price_monthly',
        'price_yearly',
        'is_purchasable',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'is_purchasable' => 'boolean',
            'sort' => 'integer',
        ];
    }

    // Plans with a pricing card, in display order.
    public function scopeListed(Builder $query): void
    {
        $query->orderBy('sort');
    }
}
