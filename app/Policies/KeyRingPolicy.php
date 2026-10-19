<?php

namespace App\Policies;

use App\Models\KeyRing;
use App\Models\User;

class KeyRingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function view(User $user, KeyRing $keyRing): bool
    {
        return $user->isSystemAdmin() || $keyRing->holder_id === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function update(User $user, KeyRing $keyRing): bool
    {
        return $user->isSystemAdmin();
    }

    public function delete(User $user, KeyRing $keyRing): bool
    {
        return $user->isSystemAdmin();
    }
}
