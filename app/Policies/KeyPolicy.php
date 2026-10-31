<?php

namespace App\Policies;

use App\Models\Key;
use App\Models\Team;
use App\Models\User;

class KeyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function view(User $user, Key $key): bool
    {
        if ($user->isSystemAdmin() || $key->holder_id === $user->getKey()) {
            return true;
        }

        return $key->interestedTeams()->contains(
            fn (Team $team): bool => $user->manages($team),
        );
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function update(User $user, Key $key): bool
    {
        return $user->isSystemAdmin();
    }

    public function delete(User $user, Key $key): bool
    {
        return $user->isSystemAdmin();
    }

    public function pass(User $user, Key $key): bool
    {
        return $key->canBePassedBy($user);
    }
}
