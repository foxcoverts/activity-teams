<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Events\BookingInvite;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;

class InviteAttendeesAction
{
    /**
     * @param  iterable<string>|array<int, string>  $userIds
     */
    public function __invoke(Booking $booking, iterable $userIds, User $actor): void
    {
        if (! $booking->acceptsInvites()) {
            return;
        }

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user || $user->isSuspended() || ! $user->hasVerifiedEmail()) {
                continue;
            }

            $booking->attendees()->syncWithoutDetaching([
                $user->id => [
                    'status' => BookingAttendeeStatus::NeedsAction->value,
                    'token' => BookingAttendance::generateToken(),
                ],
            ]);

            event(new BookingInvite($booking, $user));
        }

        activity()
            ->causedBy($actor)
            ->performedOn($booking)
            ->withProperties(['user_ids' => collect($userIds)->values()->all()])
            ->event('attendees_invited')
            ->log('Attendees invited');
    }
}
