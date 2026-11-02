<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Key;
use App\Models\Qualification;
use App\Models\TeamMembership;
use App\Models\User;

class ExportPersonalDataAction
{
    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $user->load([
            'memberships.team',
            'qualifications',
            'bookings.activity',
            'ledBookings.activity',
            'keys.locks',
        ]);

        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'emergency_name' => $user->emergency_name,
            'emergency_phone' => $user->emergency_phone,
            'timezone' => $user->timezoneName(),
            'under_18' => $user->isUnder18(),
            'memberships' => $user->memberships->map(fn (TeamMembership $membership): array => [
                'team' => $membership->team?->name,
                'role' => $membership->role?->value,
                'left_at' => $membership->left_at?->toDateString(),
            ])->all(),
            'qualifications' => $user->qualifications->map(fn (Qualification $qualification): array => [
                'kind' => $qualification->kind,
                'payload' => $qualification->payload,
                'expires_on' => $qualification->expires_on?->toDateString(),
            ])->all(),
            'bookings' => $user->bookings->map(fn (Booking $booking): array => [
                'activity' => $booking->activityTitle(),
                'start_at' => $booking->start_at?->toIso8601String(),
                'end_at' => $booking->end_at?->toIso8601String(),
                'status' => $booking->attendance?->status?->value,
                'role' => 'attendee',
            ])->concat($user->ledBookings->map(fn (Booking $booking): array => [
                'activity' => $booking->activityTitle(),
                'start_at' => $booking->start_at?->toIso8601String(),
                'end_at' => $booking->end_at?->toIso8601String(),
                'status' => $booking->status?->value,
                'role' => 'lead',
            ]))->values()->all(),
            'keys' => $user->keys->map(fn (Key $ring): array => [
                'name' => $ring->name,
                'locks' => $ring->locks->pluck('name')->all(),
            ])->all(),
        ];
    }
}
