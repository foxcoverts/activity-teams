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
        ?string $comment = null,
        ?string $via = null,
    ): void {
        $attendeeId = is_string($attendee) ? $attendee : $attendee->id;
        $status = is_string($status) ? BookingAttendeeStatus::from($status) : $status;

        if ($status === BookingAttendeeStatus::NeedsAction) {
            throw new InvalidArgumentException('You cannot return to awaiting response.');
        }

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

        $properties = [
            'attendee_id' => $attendeeId,
            'status' => $status->value,
            'comment' => $comment,
        ];

        if (filled($via)) {
            $properties['via'] = $via;
        }

        activity()
            ->causedBy($author)
            ->performedOn($this->booking)
            ->withProperties($properties)
            ->event('attendance_updated')
            ->log('Attendance updated');

        if ($this->booking->lead_instructor_id !== $attendeeId || $status === BookingAttendeeStatus::Accepted) {
            return;
        }

        (new ClearLeadInstructorAction)($this->booking, $author);
    }
}
