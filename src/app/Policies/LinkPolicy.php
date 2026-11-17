<?php

namespace App\Policies;

use App\Models\Link;
use App\Models\User;

class LinkPolicy
{
    public function view(User $user, Link $link): bool
    {
        return $this->owns($user, $link);
    }

    public function update(User $user, Link $link): bool
    {
        return $this->owns($user, $link);
    }

    public function delete(User $user, Link $link): bool
    {
        return $this->owns($user, $link);
    }

    public function disable(User $user, Link $link): bool
    {
        return $user->can('links.update');
    }

    public function enable(User $user, Link $link): bool
    {
        return $user->can('links.update');
    }

    private function owns(User $user, Link $link): bool
    {
        return $link->user_id !== null && (string) $link->user_id === (string) $user->getKey();
    }
}
