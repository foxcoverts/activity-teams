<?php

namespace App\Actions;

use App\Enums\CalendarMethod;
use App\Enums\MailChannel;
use App\Mail\BookingIcsRefreshMail;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingHeldNotification;
use App\Support\BookingIcsFactory;
use App\Support\MailPreference;
use Illuminate\Support\Facades\Mail;

class ResendBookingIcsAction
{
    public function __invoke(Booking $booking, User $attendee): void
    {
        if ($attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
            return;
        }

        $booking->loadMissing(['activity', 'venue', 'attendees', 'related']);

        if (! MailPreference::allows($attendee, MailChannel::BookingsMail, $booking)) {
            $attendee->notify(new BookingHeldNotification(
                $booking,
                __('A calendar copy of :activity is in your inbox.', [
                    'activity' => $booking->activity->name,
                ]),
            ));

            return;
        }

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
