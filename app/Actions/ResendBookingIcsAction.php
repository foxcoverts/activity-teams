<?php

namespace App\Actions;

use App\Enums\CalendarMethod;
use App\Enums\MailChannel;
use App\Mail\BookingIcsRefreshMail;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingHeldNotification;
use App\Support\MailPreference;
use Illuminate\Support\Facades\Mail;

class ResendBookingIcsAction
{
    public function __invoke(Booking $booking, User $attendee, bool $force = false): void
    {
        if ($attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
            return;
        }

        $booking->loadMissing(['activity', 'venue', 'attendees', 'related']);

        $channelActive = $booking->attendeeHasCalendarChannel($attendee);

        if (! $force && ! $channelActive && ! MailPreference::allows($attendee, MailChannel::BookingsMail, $booking)) {
            $attendee->notify(new BookingHeldNotification(
                $booking,
                __('A calendar copy of :activity is in your inbox.', [
                    'activity' => $booking->activityTitle(),
                ]),
            ));

            return;
        }

        $method = $booking->isCancelled()
            ? CalendarMethod::Cancel
            : CalendarMethod::Request;

        Mail::to($attendee)->send(new BookingIcsRefreshMail($booking, $attendee, $method));
    }
}
