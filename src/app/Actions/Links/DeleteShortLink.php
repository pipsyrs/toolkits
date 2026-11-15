<?php

namespace App\Actions\Links;

use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteShortLink
{
    public function handle(User $user, Link $link): void
    {
        Gate::forUser($user)->authorize('delete', $link);

        $link->delete();
    }
}
