<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Auth\SessionRevoker;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

// Self-service 2FA changes: audit (actor = the user) + revoke other sessions.
class AuditTwoFactorChanges
{
    public function __construct(
        protected AuditLogger $audit,
        protected SessionRevoker $sessions,
    ) {}

    public function confirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->record($event->user, 'user.2fa_enabled', revoke: true);
    }

    public function disabled(TwoFactorAuthenticationDisabled $event): void
    {
        // Cancelling an unconfirmed setup is not a security change.
        if (($event->user->getPrevious()['two_factor_confirmed_at'] ?? null) !== null) {
            $this->record($event->user, 'user.2fa_disabled', revoke: true);
        }
    }

    public function recoveryCodes(RecoveryCodesGenerated $event): void
    {
        $this->record($event->user, 'user.2fa_recovery_regenerated', revoke: false);
    }

    /**
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TwoFactorAuthenticationConfirmed::class => 'confirmed',
            TwoFactorAuthenticationDisabled::class => 'disabled',
            RecoveryCodesGenerated::class => 'recoveryCodes',
        ];
    }

    private function record(mixed $user, string $action, bool $revoke): void
    {
        if (! $user instanceof User) {
            return;
        }

        $request = request();
        $this->audit->log($user, $action, $user, [], null, $request->ip());

        // Keep only the session that made the change.
        if ($revoke && $request->hasSession()) {
            $this->sessions->revoke($user, $request->session()->getId());
        }
    }
}
