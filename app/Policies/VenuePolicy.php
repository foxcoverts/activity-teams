<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdmin() || $user->can('manage', Booking::class);
    }

    public function view(User $user, Venue $venue): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function update(User $user, Venue $venue): bool
    {
        return $user->isSystemAdmin();
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->isSystemAdmin();
    }
}
