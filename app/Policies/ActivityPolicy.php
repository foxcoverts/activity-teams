<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\Booking;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdmin() || $user->can('manage', Booking::class);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->isSystemAdmin();
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->isSystemAdmin();
    }
}
