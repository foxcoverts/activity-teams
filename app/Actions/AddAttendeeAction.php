<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\DeclinedSessions;
use Illuminate\Validation\ValidationException;

class AddAttendeeAction
{
    public function __invoke(
        Booking $booking,
        User $attendee,
        User $manager,
        BookingAttendeeStatus|string $status,
        ?string $comment = null,
    ): void {
        $status = is_string($status) ? BookingAttendeeStatus::from($status) : $status;

        if ($booking->isCancelled()) {
            throw ValidationException::withMessages([
                'user_id' => __('Attendance cannot be changed on a cancelled booking.'),
            ]);
        }

        if ($status === BookingAttendeeStatus::NeedsAction) {
            throw ValidationException::withMessages([
                'response' => __('Choose can help, maybe, or cannot make it.'),
            ]);
        }

        $booking->loadMissing('team');

        if ($attendee->isSuspended() || $booking->team === null || ! $attendee->isCurrentMemberOf($booking->team)) {
            throw ValidationException::withMessages([
                'user_id' => __('Choose a member who can be added to the booking.'),
            ]);
        }

        $existing = $booking->attendees()->whereKey($attendee->getKey())->first();

        if ($existing !== null) {
            if (! $existing->attendance?->isEligibleForReinvite($booking)) {
                throw ValidationException::withMessages([
                    'user_id' => __('This person is already on the booking.'),
                ]);
            }

            app(OverrideAttendanceAction::class)($booking, $attendee, $manager, $status, $comment);

            return;
        }

        $pivot = [
            'status' => $status->value,
            'comment' => filled($comment) ? $comment : null,
        ];

        if ($status === BookingAttendeeStatus::Declined) {
            $pivot['declined_sessions'] = DeclinedSessions::fromBooking($booking)->toArray();
        }

        $booking->attendees()->attach($attendee->getKey(), $pivot);

        app(OverrideAttendanceAction::class)($booking, $attendee, $manager, $status, $comment);
    }
}
