<?php

namespace App\Policies;

use App\Models\Key;
use App\Models\User;

class KeyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function view(User $user, Key $key): bool
    {
        return $user->isSystemAdmin();
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
}
