<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;

class AttendanceResponseLinks
{
    /**
     * @return list<array{label: string, url: string, color: string}>
     */
    public static function choices(Booking $booking, User $attendee, ?BookingAttendance $attendance): array
    {
        if (! $attendance?->token || ! $booking->acceptsInvites()) {
            return [];
        }

        if (! $attendance->needsAction() && $attendance->status !== BookingAttendeeStatus::Tentative) {
            return [];
        }

        return [
            self::choice(__('Yes'), $booking, $attendee, $attendance->token, BookingAttendeeStatus::Accepted, '#48bb78'),
            self::choice(__('No'), $booking, $attendee, $attendance->token, BookingAttendeeStatus::Declined, '#e53e3e'),
            self::choice(__('Maybe'), $booking, $attendee, $attendance->token, BookingAttendeeStatus::Tentative, '#2d3748'),
        ];
    }

    /**
     * @return array{label: string, url: string, color: string}
     */
    private static function choice(
        string $label,
        Booking $booking,
        User $attendee,
        string $token,
        BookingAttendeeStatus $status,
        string $color,
    ): array {
        return [
            'label' => $label,
            'url' => self::url($booking, $attendee, $token, $status),
            'color' => $color,
        ];
    }

    public static function url(Booking $booking, User $attendee, string $token, BookingAttendeeStatus $choice): string
    {
        return route('respond', [
            $booking,
            $attendee,
            'invite' => $token,
            'choice' => $choice->value,
            'sequence' => $booking->sequence,
        ]);
    }
}
