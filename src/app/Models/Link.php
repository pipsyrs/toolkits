<?php

namespace App\Models;

use Database\Factories\LinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'guest_id',
        'code',
        'is_custom',
        'target_url',
        'target_host',
        'password_hash',
        'expires_at',
        'disabled_at',
        'disabled_reason',
        'created_tier',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
            'expires_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_clicked_at' => 'datetime',
            'clicks_total' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Not disabled and not expired.
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('disabled_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    protected function shortUrl(): Attribute
    {
        return Attribute::get(fn () => $this->code ? route('links.redirect', ['code' => $this->code]) : null);
    }
}
