<?php

namespace App\Models;

use App\Enums\FileOperationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileOperation extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'guest_id',
        'tool',
        'status',
        'options',
        'inputs',
        'quota_subjects',
        'expires_at',
    ];

    protected $hidden = [
        'inputs',
        'quota_subjects',
        'output_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => FileOperationStatus::class,
            'options' => 'array',
            'inputs' => 'array',
            'quota_subjects' => 'array',
            'output_size' => 'integer',
            'expires_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Logged-in owner by user id, otherwise by guest cookie id.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOwnedBy(Builder $query, ?User $user, string $guestId): void
    {
        $user !== null
            ? $query->where('user_id', $user->getKey())
            : $query->whereNull('user_id')->where('guest_id', $guestId);
    }

    /**
     * Not expired yet; null expires_at = kept permanently.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUnexpired(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function directory(): string
    {
        return config('toolkits.files.directory').'/'.$this->getKey();
    }

    // Total bytes uploaded; inputs keep name and size after the files are deleted.
    public function inputSize(): int
    {
        return (int) array_sum(array_column((array) $this->inputs, 'size'));
    }

    public function isDownloadable(): bool
    {
        return $this->status === FileOperationStatus::Done
            && $this->output_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
