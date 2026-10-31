<?php

namespace App\Policies;

use App\Models\Lock;
use App\Models\User;

class LockPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function view(User $user, Lock $lock): bool
    {
        return $user->isSystemAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function update(User $user, Lock $lock): bool
    {
        return $user->isSystemAdmin();
    }

    public function delete(User $user, Lock $lock): bool
    {
        return $user->isSystemAdmin();
    }
}
