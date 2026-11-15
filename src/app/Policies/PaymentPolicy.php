<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    // Owner only (also for superadmin: no Gate::before on non-catalog abilities).
    public function view(User $user, Payment $payment): bool
    {
        return $payment->user_id !== null && (string) $payment->user_id === (string) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->blocked_at === null;
    }
}
