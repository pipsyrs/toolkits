<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionRevoker
{
    // Delete DB sessions of the user (except $keepSessionId) and rotate remember_token.
    public function revoke(User $user, ?string $keepSessionId = null): void
    {
        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->when($keepSessionId !== null, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
