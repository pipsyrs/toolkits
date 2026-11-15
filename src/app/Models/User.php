<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'blocked_at' => 'datetime',
            'plan_until' => 'datetime',
            'plan_reminder_for' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function supportReports(): HasMany
    {
        return $this->hasMany(SupportReport::class);
    }

    // False for Google-only accounts.
    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    public const SUPERADMIN_ROLE = 'superadmin';

    // Only place the superadmin role name is referenced; protects super accounts, not actor checks.
    public function isSuperadmin(): bool
    {
        return $this->hasRole(self::SUPERADMIN_ROLE);
    }
}
