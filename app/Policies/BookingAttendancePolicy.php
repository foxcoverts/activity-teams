<?php

namespace App\Policies;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Carbon\CarbonImmutable;

class BookingAttendancePolicy
{
    public function viewAny(User $user, Booking $booking): bool
    {
        return $user->can('view', $booking);
    }

    public function create(User $user, Booking $booking): bool
    {
        return $this->managesBooking($user, $booking);
    }

    public function view(User $user, BookingAttendance $attendance): bool
    {
        return $user->can('update', $attendance) || $user->can('view', $attendance->booking);
    }

    public function update(User $user, BookingAttendance $attendance): bool
    {
        $booking = $attendance->booking;
        if ($booking->isPast() || $booking->isCancelled()) {
            return false;
        }
        if ($attendance->user?->isSuspended()) {
            return false;
        }
        if ($this->managesBooking($user, $booking)) {
            return true;
        }

        return $attendance->user?->is($user) && $attendance->exists;
    }

    public function override(User $user, BookingAttendance $attendance): bool
    {
        $booking = $attendance->pivotParent instanceof Booking
            ? $attendance->pivotParent
            : $attendance->booking;

        if ($booking->isCancelled()) {
            return false;
        }

        $attendance->loadMissing('user');

        if ($attendance->user?->isSuspended()) {
            return false;
        }

        return $this->managesBooking($user, $booking);
    }

    public function delete(User $user, BookingAttendance $attendance): bool
    {
        if ($attendance->isLeadInstructor() && $attendance->isAccepted()) {
            return false;
        }

        $booking = $attendance->pivotParent instanceof Booking
            ? $attendance->pivotParent
            : $attendance->booking;

        return $this->managesBooking($user, $booking);
    }

    /**
     * Phone numbers: attendees and the lead can reach each other, and attendees can reach an
     * attending team manager, until the booking's last day ends. There is no earlier start.
     */
    public function viewPhone(User $viewer, BookingAttendance $attendance): bool
    {
        [$booking, $subject] = $this->contactParties($attendance);

        if (! $this->canContact($viewer, $subject, $booking) || now()->greaterThan($this->windowEnd($booking))) {
            return false;
        }

        return $booking->isLead($viewer)
            || $booking->isLead($subject)
            || ($booking->team !== null && $subject->manages($booking->team));
    }

    /**
     * Emergency contacts: attendees and the lead, only on the booking's own dates.
     */
    public function viewEmergencyContact(User $viewer, BookingAttendance $attendance): bool
    {
        [$booking, $subject] = $this->contactParties($attendance);

        if (! $this->canContact($viewer, $subject, $booking)) {
            return false;
        }

        $now = now();

        if ($now->lessThan($this->windowStart($booking)) || $now->greaterThan($this->windowEnd($booking))) {
            return false;
        }

        return $booking->isLead($viewer) || $booking->isLead($subject);
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    private function contactParties(BookingAttendance $attendance): array
    {
        // An eager-loaded pivot only has an empty Booking as its parent.
        $booking = $attendance->pivotParent instanceof Booking && $attendance->pivotParent->exists
            ? $attendance->pivotParent
            : $attendance->booking;
        $booking->loadMissing('team');
        $attendance->loadMissing('user');

        return [$booking, $attendance->user];
    }

    private function canContact(User $viewer, ?User $subject, Booking $booking): bool
    {
        if (
            $subject === null
            || $viewer->is($subject)
            || $booking->isCancelled()
            || $booking->start_at === null
            || $booking->end_at === null
            || $viewer->isSuspended()
            || $subject->isSuspended()
        ) {
            return false;
        }

        return $this->isAttending($booking, $viewer) && $this->isAttending($booking, $subject);
    }

    private function isAttending(Booking $booking, User $user): bool
    {
        $booking->loadMissing('attendees');

        return in_array(
            $booking->attendees->find($user->getKey())?->attendance?->status,
            [BookingAttendeeStatus::Accepted, BookingAttendeeStatus::Tentative],
            true,
        );
    }

    private function windowStart(Booking $booking): CarbonImmutable
    {
        return $booking->start_at->toImmutable()->setTimezone($booking->timezone)->startOfDay();
    }

    private function windowEnd(Booking $booking): CarbonImmutable
    {
        return $booking->end_at->toImmutable()->setTimezone($booking->timezone)->endOfDay();
    }

    private function managesBooking(User $user, Booking $booking): bool
    {
        $booking->loadMissing('team');

        return $booking->team !== null && $user->manages($booking->team);
    }
}
