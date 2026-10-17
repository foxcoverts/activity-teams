<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;

class BookingPolicy
{
    public function manage(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return $user->isTeamLeader() || $user->isBookingManager();
    }

    public function viewAny(User $user, ?BookingStatus $status = null): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return match ($status) {
            BookingStatus::Tentative, BookingStatus::Cancelled => $this->manage($user),
            default => $this->manage($user) || ! $user->isGuest(),
        };
    }

    public function viewOwn(User $user): bool
    {
        return $user->exists && ! $user->isSuspended();
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        if ($this->attendee($booking, $user)) {
            return true;
        }

        return $this->viewAny($user, $booking->status);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Booking $booking): bool
    {
        return $this->manage($user);
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $user->isTeamLeader() && $this->manage($user);
    }

    public function respond(User $user, Booking $booking, User $model): bool
    {
        if ($user->isSuspended() || $model->isSuspended()) {
            return false;
        }

        $attendee = $this->attendee($booking, $model);
        $attendance = $attendee
            ? $attendee->attendance
            : BookingAttendance::build($booking, $model);

        return $user->can('update', $attendance);
    }

    private function attendee(Booking $booking, User $user): ?User
    {
        if ($booking->relationLoaded('attendees')) {
            return $booking->attendees->find($user);
        }

        return $booking->attendees()->find($user);
    }
}
