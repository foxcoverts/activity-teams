<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingChanged;
use App\Events\BookingConfirmed;
use App\Events\BookingInvite;
use App\Events\BookingRestored;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;

class UpdateBookingStatusAction
{
    public function __invoke(Booking $booking, User $actor, ?string $cancelReason = null): void
    {
        $originalStatus = $booking->getOriginal('status');
        $originalStatus = $originalStatus instanceof BookingStatus
            ? $originalStatus
            : BookingStatus::tryFrom((string) $originalStatus);

        if ($originalStatus === $booking->status) {
            if ($booking->wasChanged([
                'sequence',
                'group_name',
                'notes',
                'lead_instructor_id',
                'timezone',
            ])) {
                event(new BookingChanged($booking, $actor, $booking->getChanges(), $this->previousSchedule($booking)));
            }

            return;
        }

        if ($originalStatus === BookingStatus::Cancelled && $booking->status !== BookingStatus::Cancelled) {
            $booking->lead_instructor_id = null;
            $booking->save();

            $attendees = $booking->attendees->mapWithKeys(function (User $attendee) {
                if ($attendee->isSuspended()) {
                    return [];
                }
                if ($attendee->attendance->status === BookingAttendeeStatus::Declined) {
                    return [$attendee->id => ['status' => BookingAttendeeStatus::Declined->value]];
                }
                if ($attendee->hasVerifiedEmail()) {
                    return [$attendee->id => ['status' => BookingAttendeeStatus::NeedsAction->value]];
                }

                return [];
            });
            $booking->attendees()->sync($attendees->all());
            $booking->refresh();

            $attendees
                ->filter(fn (array $meta) => $meta['status'] === BookingAttendeeStatus::NeedsAction->value)
                ->keys()
                ->each(function (string $id) use ($booking) {
                    $user = User::find($id);
                    if ($user) {
                        event(new BookingInvite($booking, $user));
                    }
                });

            event(new BookingRestored($booking, $actor, $booking->getChanges(), $this->previousSchedule($booking)));

            return;
        }

        if ($booking->isConfirmed()) {
            event(new BookingConfirmed($booking, $actor, $booking->getChanges(), $this->previousSchedule($booking)));
        } elseif ($booking->isCancelled()) {
            BookingAttendance::query()
                ->where('booking_id', $booking->id)
                ->where('status', BookingAttendeeStatus::NeedsAction)
                ->delete();
            $booking->refresh();
            event(new BookingCancelled($booking, $actor, $cancelReason));
        }
    }

    /**
     * @return array{start_at: mixed, end_at: mixed, timezone: mixed}|null
     */
    private function previousSchedule(Booking $booking): ?array
    {
        $changed = array_intersect(
            ['start_at', 'end_at', 'timezone'],
            array_keys($booking->getChanges()),
        );

        if ($changed === []) {
            return null;
        }

        $previous = $booking->getPrevious();

        return [
            'start_at' => $previous['start_at'] ?? $booking->getRawOriginal('start_at'),
            'end_at' => $previous['end_at'] ?? $booking->getRawOriginal('end_at'),
            'timezone' => $previous['timezone'] ?? $booking->getRawOriginal('timezone'),
        ];
    }
}
