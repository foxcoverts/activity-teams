<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class BookingPolicy
{
    public function manage(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $tenant = Filament::getTenant();

        if ($tenant instanceof Team) {
            return $user->manages($tenant);
        }

        return $user->managedTeams()->exists();
    }

    public function viewAny(User $user, ?BookingStatus $status = null): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return match ($status) {
            BookingStatus::Tentative, BookingStatus::Cancelled => $this->manage($user),
            default => $user->teams()->exists() || $this->manage($user),
        };
    }

    public function viewOwn(User $user): bool
    {
        return $user->exists && ! $user->isSuspended();
    }

    public function view(User $user, Booking $booking): bool
    {
        $booking->loadMissing('team');

        if ($user->isSuspended() || $booking->team === null) {
            return false;
        }

        if ($user->manages($booking->team)) {
            return true;
        }

        if ($this->attendee($booking, $user)) {
            return true;
        }

        return $booking->status === BookingStatus::Confirmed && $user->isCurrentMemberOf($booking->team);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Booking $booking): bool
    {
        $booking->loadMissing('team');

        return $booking->team !== null && $user->manages($booking->team);
    }

    public function delete(User $user, Booking $booking): bool
    {
        return $booking->isCancelled() && $this->update($user, $booking);
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
            $match = $booking->attendees->find($user->getKey());

            return $match instanceof User ? $match : null;
        }

        $match = $booking->attendees()->whereKey($user->getKey())->first();

        return $match instanceof User ? $match : null;
    }
}
