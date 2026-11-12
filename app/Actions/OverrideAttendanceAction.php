<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\AttendanceRecordedNotification;
use App\Support\DeclinedSessions;
use Illuminate\Validation\ValidationException;

class OverrideAttendanceAction
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
                'status' => __('Attendance cannot be changed on a cancelled booking.'),
            ]);
        }

        if ($status === BookingAttendeeStatus::NeedsAction) {
            throw ValidationException::withMessages([
                'status' => __('Choose can help, maybe, or cannot make it.'),
            ]);
        }

        $existing = $booking->attendees()->find($attendee->getKey())?->attendance;

        if ($existing === null) {
            throw ValidationException::withMessages([
                'status' => __('This person is not on the booking.'),
            ]);
        }

        $comment = filled($comment) ? $comment : null;

        $pivot = [
            'status' => $status->value,
            'comment' => $comment,
        ];

        if ($status === BookingAttendeeStatus::Declined) {
            $pivot['declined_sessions'] = DeclinedSessions::from($existing->declined_sessions)
                ->withBooking($booking)
                ->toArray();
        }

        $booking->attendees()->syncWithoutDetaching([
            $attendee->getKey() => $pivot,
        ]);

        $clearLead = $booking->lead_instructor_id === $attendee->getKey()
            && $status !== BookingAttendeeStatus::Accepted;

        $properties = [
            'attendee_id' => $attendee->getKey(),
            'status' => $status->value,
            'comment' => $comment,
            'via' => 'manager',
        ];

        if ($clearLead) {
            activity()->withoutLogging(
                fn () => (new ClearLeadInstructorAction)($booking, $manager),
            );

            $booking->refresh();
            $properties['booking_status'] = $booking->status->value;

            activity()
                ->causedBy($manager)
                ->performedOn($booking)
                ->withProperties($properties)
                ->event('lead_stepped_down')
                ->log('lead_stepped_down');
        } else {
            activity()
                ->causedBy($manager)
                ->performedOn($booking)
                ->withProperties($properties)
                ->event('attendance_updated')
                ->log(__('Recorded :status for :name.', [
                    'status' => $status->getLabel(),
                    'name' => $attendee->name,
                ]));
        }

        if ($booking->isPast() || $attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
            return;
        }

        $attendee->notify(new AttendanceRecordedNotification($booking, $attendee));
    }
}
