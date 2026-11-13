<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Enums\TeamFeature;
use App\Events\BookingConfirmed;
use App\Models\Booking;
use App\Models\User;
use App\Support\ClearLeadNeeded;
use App\Support\ManagerAttention;

class ConfirmSessionOccurredAction
{
    /**
     * When Debrief is on, a rollcall or debrief submit that records attendance also confirms a
     * still-tentative booking that already has a lead (session happened path).
     */
    public function __invoke(Booking $booking, User $actor): bool
    {
        $booking->loadMissing('team');

        if (
            ! $booking->isTentative()
            || $booking->lead_instructor_id === null
            || $booking->team === null
            || ! $booking->team->featureEnabled(TeamFeature::Debrief)
        ) {
            return false;
        }

        $booking->status = BookingStatus::Confirmed;
        $booking->save();

        app(ClearLeadNeeded::class)($booking->fresh());
        ManagerAttention::flush($booking->team_id);

        event(new BookingConfirmed(
            $booking->fresh(),
            $actor,
            ['status' => BookingStatus::Confirmed->value],
            previousSchedule: null,
        ));

        return true;
    }
}
