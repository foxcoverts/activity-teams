<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage', \App\Models\Booking::class);
    }

    public function view(User $user, Venue $venue): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Venue $venue): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->isTeamLeader();
    }
}
