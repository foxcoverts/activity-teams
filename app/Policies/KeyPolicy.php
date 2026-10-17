<?php

namespace App\Policies;

use App\Models\Key;
use App\Models\User;

class KeyPolicy
{
    public function manage(User $user): bool
    {
        return $user->isTeamLeader() || $user->isUserManager();
    }

    public function viewAny(User $user): bool
    {
        return $this->manage($user) || $user->keys()->exists();
    }

    public function view(User $user, Key $key): bool
    {
        return $this->manage($user) || $key->holder_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Key $key): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Key $key): bool
    {
        return $user->isTeamLeader();
    }
}
