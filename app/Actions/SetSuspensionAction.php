<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SetSuspensionAction
{
    /**
     * Suspension is system-wide and silent: nothing is sent to the member and
     * their memberships, profile and attendance are left untouched. The one
     * exception is leading upcoming bookings: they are unassigned, and a
     * confirmed booking returns to tentative.
     */
    public function __invoke(User $actor, User $member, bool $suspended): void
    {
        Gate::forUser($actor)->authorize('suspend', $member);

        if ($member->isSuspended() === $suspended) {
            return;
        }

        $member->forceFill(['suspended' => $suspended])->save();

        if ($suspended) {
            $this->unassignUpcomingLeads($actor, $member);
        }
    }

    private function unassignUpcomingLeads(User $actor, User $member): void
    {
        $member->ledBookings()->notCancelled()->future()->get()->each(
            fn (Booking $booking): bool => (new ClearLeadInstructorAction)($booking, $actor),
        );
    }
}
