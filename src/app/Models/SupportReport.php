<?php

namespace App\Models;

use App\Enums\SupportReportCategory;
use App\Enums\SupportReportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReport extends Model
{
    use HasUuids;

    // status, admin_reply, handled_by, resolved_at: set via forceFill in Actions only.
    protected $fillable = [
        'user_id',
        'category',
        'subject',
        'message',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'category' => SupportReportCategory::class,
            'status' => SupportReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
