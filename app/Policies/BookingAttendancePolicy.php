<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;

class BookingAttendancePolicy
{
    public function viewAny(User $user, Booking $booking): bool
    {
        return $user->can('view', $booking);
    }

    public function create(User $user, Booking $booking): bool
    {
        return $user->can('manage', Booking::class);
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
        if ($attendance->isLeadInstructor() && $attendance->isAccepted()) {
            return false;
        }
        if ($user->can('manage', Booking::class)) {
            return true;
        }

        return $attendance->user?->is($user) && $attendance->exists;
    }

    public function delete(User $user, BookingAttendance $attendance): bool
    {
        if ($attendance->isLeadInstructor() && $attendance->isAccepted()) {
            return false;
        }

        return $user->can('manage', Booking::class);
    }
}
