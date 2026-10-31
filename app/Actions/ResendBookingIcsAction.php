<?php

namespace App\Actions;

use App\Enums\CalendarMethod;
use App\Mail\BookingIcsRefreshMail;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingIcsFactory;
use Illuminate\Support\Facades\Mail;

class ResendBookingIcsAction
{
    public function __invoke(Booking $booking, User $attendee): void
    {
        if ($attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
            return;
        }

        $booking->loadMissing(['activity', 'venue', 'attendees', 'related']);

        $method = $booking->isCancelled()
            ? CalendarMethod::Cancel
            : CalendarMethod::Request;

        $ics = app(BookingIcsFactory::class)->make(
            [$booking],
            $booking->activity->name,
            method: $method,
            forAttendee: $attendee,
        );

        Mail::to($attendee)->send(new BookingIcsRefreshMail($booking, $attendee, $ics, $method));
    }
}
