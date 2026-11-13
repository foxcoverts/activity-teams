<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Support\DeclinedSessions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmAttendanceAction
{
    public const MARK_CONFIRM = 'confirm';

    public const MARK_SKIP = 'skip';

    public const MARK_DECLINE = 'decline';

    /**
     * @param  array<string, string>  $marks  user id => confirm|skip|decline
     */
    public function __invoke(Booking $booking, User $actor, array $marks): void
    {
        if ($booking->isCancelled()) {
            throw ValidationException::withMessages([
                'marks' => __('Attendance cannot be confirmed on a cancelled booking.'),
            ]);
        }

        if (! $booking->isOnOrAfterSessionDay()) {
            throw ValidationException::withMessages([
                'marks' => __('Attendance can be confirmed from the session day onwards.'),
            ]);
        }

        if (! $booking->isLead($actor) && ($booking->team === null || ! $actor->manages($booking->team))) {
            throw ValidationException::withMessages([
                'marks' => __('Only the lead instructor or a team manager can confirm attendance.'),
            ]);
        }

        $booking->loadMissing('attendees');

        DB::transaction(function () use ($booking, $actor, $marks): void {
            foreach ($marks as $userId => $mark) {
                if (! is_string($mark) || $mark === self::MARK_SKIP) {
                    continue;
                }

                if (! in_array($mark, [self::MARK_CONFIRM, self::MARK_DECLINE], true)) {
                    throw ValidationException::withMessages([
                        'marks' => __('Choose attended, skip, or did not attend.'),
                    ]);
                }

                $attendee = $booking->attendees->find($userId);

                if ($attendee === null) {
                    $attendee = User::query()->find($userId);

                    if (! $attendee instanceof User) {
                        throw ValidationException::withMessages([
                            'marks' => __('This person could not be found.'),
                        ]);
                    }

                    $booking->attendees()->syncWithoutDetaching([
                        $attendee->getKey() => [
                            'status' => BookingAttendeeStatus::NeedsAction->value,
                            'token' => BookingAttendance::generateToken(),
                        ],
                    ]);

                    $booking->unsetRelation('attendees');
                    $booking->load('attendees');
                    $attendee = $booking->attendees->find($userId);
                }

                if ($attendee === null || ! $attendee->attendance instanceof BookingAttendance) {
                    throw ValidationException::withMessages([
                        'marks' => __('This person is not on the booking.'),
                    ]);
                }

                $this->applyMark($booking, $attendee, $actor, $mark);
            }
        });
    }

    private function applyMark(Booking $booking, User $attendee, User $actor, string $mark): void
    {
        $existing = $attendee->attendance;
        $previousStatus = $existing->status?->value;
        $status = $mark === self::MARK_CONFIRM
            ? BookingAttendeeStatus::Accepted
            : BookingAttendeeStatus::Declined;

        $pivot = [
            'status' => $status->value,
            'attendance_confirmed_at' => $mark === self::MARK_CONFIRM ? now() : null,
        ];

        if ($status === BookingAttendeeStatus::Declined) {
            $pivot['declined_sessions'] = DeclinedSessions::from($existing->declined_sessions)
                ->withBooking($booking)
                ->toArray();
        }

        $booking->attendees()->syncWithoutDetaching([
            $attendee->getKey() => $pivot,
        ]);

        $statusChanged = $previousStatus !== $status->value;

        $clearLead = $booking->lead_instructor_id === $attendee->getKey()
            && $status !== BookingAttendeeStatus::Accepted;
        $newlyConfirmed = $mark === self::MARK_CONFIRM && ! $existing->isAttendanceConfirmed();

        $properties = [
            'attendee_id' => $attendee->getKey(),
            'status' => $status->value,
            'via' => 'rollcall',
        ];

        if ($clearLead) {
            activity()->withoutLogging(
                fn () => (new ClearLeadInstructorAction)($booking, $actor),
            );

            $booking->refresh();
            $properties['booking_status'] = $booking->status->value;

            activity()
                ->causedBy($actor)
                ->performedOn($booking)
                ->withProperties($properties)
                ->event('lead_stepped_down')
                ->log('lead_stepped_down');
        } elseif ($statusChanged || $newlyConfirmed) {
            activity()
                ->causedBy($actor)
                ->performedOn($booking)
                ->withProperties($properties)
                ->event('attendance_confirmed')
                ->log(__('Confirmed :status for :name.', [
                    'status' => $status->getLabel(),
                    'name' => $attendee->name,
                ]));
        }

        if ($statusChanged && ! $attendee->isSuspended() && $attendee->hasVerifiedEmail()) {
            app(ResendBookingIcsAction::class)($booking, $attendee, force: true);
        }
    }
}
