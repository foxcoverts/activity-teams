<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Mail\AttendanceRecordedMail;
use App\Models\Booking;
use App\Models\User;
use App\Support\MailPreference;
use Illuminate\Support\Facades\Mail;
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

        $booking->attendees()->syncWithoutDetaching([
            $attendee->getKey() => [
                'status' => $status->value,
                'comment' => $comment,
            ],
        ]);

        activity()
            ->causedBy($manager)
            ->performedOn($booking)
            ->withProperties([
                'attendee_id' => $attendee->getKey(),
                'status' => $status->value,
                'comment' => $comment,
                'via' => 'manager',
            ])
            ->event('attendance_updated')
            ->log(__('Recorded :status for :name.', [
                'status' => $status->getLabel(),
                'name' => $attendee->name,
            ]));

        $this->stepLeadAway($booking, $attendee, $manager, $status);

        if ($booking->isPast() || $attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
            return;
        }

        if (! MailPreference::allows($attendee, MailChannel::BookingsMail, $booking)) {
            return;
        }

        Mail::to($attendee)->send(new AttendanceRecordedMail($booking, $attendee));
    }

    private function stepLeadAway(Booking $booking, User $attendee, User $manager, BookingAttendeeStatus $status): void
    {
        if ($booking->lead_instructor_id !== $attendee->getKey() || $status === BookingAttendeeStatus::Accepted) {
            return;
        }

        (new ClearLeadInstructorAction)($booking, $manager);
    }
}
