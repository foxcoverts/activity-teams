<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\User;
use InvalidArgumentException;

class RespondToBookingAction
{
    public function __construct(
        public Booking $booking,
        public ?User $user = null
    ) {
        if ($booking->isPast()) {
            throw new InvalidArgumentException('You cannot change attendance on past bookings.');
        }
        if ($booking->isCancelled()) {
            throw new InvalidArgumentException('You cannot change attendance on cancelled bookings.');
        }
    }

    public function __invoke(
        User|string $attendee,
        BookingAttendeeStatus|string $status,
        ?string $comment = null
    ): void {
        $attendeeId = is_string($attendee) ? $attendee : $attendee->id;
        $status = is_string($status) ? BookingAttendeeStatus::from($status) : $status;
        $author = $this->user ?? (is_string($attendee) ? User::findOrFail($attendee) : $attendee);

        $attendance = $this->booking->attendees()->find($attendeeId)?->attendance;
        if (($attendance?->status === $status) && ($attendance?->comment === $comment)) {
            return;
        }

        $this->booking->attendees()->syncWithoutDetaching([
            $attendeeId => [
                'status' => $status->value,
                'comment' => $comment ?? $attendance?->comment,
            ],
        ]);

        activity()
            ->causedBy($author)
            ->performedOn($this->booking)
            ->withProperties([
                'attendee_id' => $attendeeId,
                'status' => $status->value,
                'comment' => $comment,
            ])
            ->event('attendance_updated')
            ->log('Attendance updated');
    }
}
