<?php

namespace App\Livewire\Concerns;

use App\Http\Middleware\EnsureGuestId;
use App\Models\User;
use App\Services\Tier\PlanLimits;
use App\Services\Tier\Tier;
use App\Services\Tier\TierResolver;
use Livewire\Attributes\Computed;

// Current visitor (user or guest cookie) and tier limits.
trait ResolvesVisitor
{
    protected function visitor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    protected function guestId(): string
    {
        return EnsureGuestId::idFrom(request());
    }

    protected function clientIp(): string
    {
        return (string) request()->ip();
    }

    #[Computed]
    public function tier(): Tier
    {
        return app(TierResolver::class)->forUser($this->visitor());
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function limits(): array
    {
        return app(PlanLimits::class)->for($this->tier);
    }

    #[Computed]
    public function isGuest(): bool
    {
        return $this->tier->isGuest();
    }
}
