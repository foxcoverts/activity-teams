<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage', \App\Models\Booking::class);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Activity $activity): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->isTeamLeader();
    }
}
