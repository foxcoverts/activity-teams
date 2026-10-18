<?php

namespace App\Policies;

use App\Models\Key;
use App\Models\User;

class KeyPolicy
{
    public function manage(User $user): bool
    {
        return $user->isSystemAdmin() || $user->managedTeams()->exists();
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
        return $user->managedTeams()->exists();
    }

    public function update(User $user, Key $key): bool
    {
        return $user->managedTeams()->exists();
    }

    public function delete(User $user, Key $key): bool
    {
        return $user->isSystemAdmin();
    }
}
