<?php

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Events\LeadInstructorCleared;
use App\Models\Booking;
use App\Models\User;
use App\Support\ManagerAttention;
use Throwable;

class ClearLeadInstructorAction
{
    public function __invoke(Booking $booking, ?User $actor = null, bool $attendeesWillBeUpdated = false): bool
    {
        if ($booking->lead_instructor_id === null) {
            return false;
        }

        $booking->loadMissing('leadInstructor');
        $previousLead = $booking->leadInstructor;
        $wasConfirmed = $booking->isConfirmed();

        $booking->lead_instructor_id = null;

        if (
            $booking->requires_lead_instructor
            && ! $booking->isPast()
            && ! $booking->isCancelled()
            && $wasConfirmed
        ) {
            $booking->status = BookingStatus::Tentative;
            $booking->lead_needed_at = now();
        }

        $cleared = $booking->isDirty('lead_instructor_id');
        $alert = $cleared && ! $booking->isPast() && ! $booking->isCancelled();

        if ($booking->isDirty(['lead_instructor_id', 'status', 'lead_needed_at'])) {
            try {
                $booking->save();
            } catch (Throwable $exception) {
                $booking->refresh();

                throw $exception;
            }

            ManagerAttention::flush($booking->team_id);
        }

        if ($alert) {
            event(new LeadInstructorCleared($booking, $previousLead, $actor, $attendeesWillBeUpdated));
        }

        return $cleared;
    }
}
