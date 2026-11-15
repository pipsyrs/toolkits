<?php

namespace App\Livewire\Concerns;

use App\Models\User;

// Checked on mount and every subsequent Livewire request; components may declare const PERMISSION.
trait AuthorizesAdmin
{
    public function bootAuthorizesAdmin(): void
    {
        $this->authorize('admin.access');

        if (defined(static::class.'::PERMISSION')) {
            $this->authorize(constant(static::class.'::PERMISSION'));
        }
    }

    protected function adminUser(): User
    {
        return auth()->user();
    }
}
