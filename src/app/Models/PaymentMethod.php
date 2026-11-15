<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

// Payment method offered in checkout; channel is a payment_channels code.
class PaymentMethod extends Model
{
    use HasUuids;

    public const LOGO_DISK = 'public';

    public const LOGO_DIRECTORY = 'payment-logos';

    protected $fillable = [
        'channel',
        'payment_method_type_id',
        'logo_path',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public static function logoUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk(self::LOGO_DISK)->url($path);
    }

    public function paymentChannel(): BelongsTo
    {
        return $this->belongsTo(PaymentChannel::class, 'channel', 'code');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PaymentMethodType::class, 'payment_method_type_id');
    }
}
