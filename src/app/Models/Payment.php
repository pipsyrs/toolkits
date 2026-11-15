<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\PaymentChannelKind;
use App\Enums\PaymentStatus;
use App\Services\Billing\PaymentChannelCatalog;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    // status, paid_at, period_start/end: set via forceFill or query updates in Actions only.
    protected $fillable = [
        'user_id',
        'order_id',
        'xendit_invoice_id',
        'xendit_payment_request_id',
        'xendit_payment_session_id',
        'invoice_url',
        'pay_code',
        'plan',
        'period',
        'kind',
        'amount',
        'currency',
        'payment_method',
        'payment_channel',
        'expires_at',
    ];

    protected $attributes = [
        'currency' => 'IDR',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'period' => BillingPeriod::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
        ];
    }

    public const DISPLAY_QR = 'qr';

    public const DISPLAY_VA = 'va';

    public const DISPLAY_LINK = 'link';

    public const DISPLAY_PUSH = 'push';

    // Null when the channel was deleted from master data; payment_channel keeps the code for history.
    public function channel(): ?PaymentChannel
    {
        return app(PaymentChannelCatalog::class)->find($this->payment_channel);
    }

    // Created by in-app checkout (payment request or card session), not a legacy invoice.
    public function isInApp(): bool
    {
        return $this->xendit_payment_request_id !== null || $this->xendit_payment_session_id !== null;
    }

    public function scopeInApp(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNotNull('xendit_payment_request_id')->orWhereNotNull('xendit_payment_session_id'));
    }

    // How the checkout modal presents pay_code (null for legacy invoices).
    public function payDisplay(): ?string
    {
        $channel = $this->channel();

        return match (true) {
            $channel === null || ! $this->isInApp() => null,
            $channel->kind === PaymentChannelKind::VirtualAccount => self::DISPLAY_VA,
            $this->pay_code === null => self::DISPLAY_PUSH,
            str_starts_with($this->pay_code, 'https://') => self::DISPLAY_LINK,
            default => self::DISPLAY_QR,
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }
}
