<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Mail\BookingStatusMail;
use Illuminate\Support\Facades\Mail;

class SendBookingCancelledEmail
{
    public function handle(BookingCancelled $event): void
    {
        foreach ($event->booking->attendees as $attendee) {
            if ($attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
                continue;
            }

            Mail::to($attendee->email)->send(new BookingStatusMail(
                $event->booking,
                $attendee,
                subject: __('Booking cancelled'),
                intro: $event->reason
                    ? __('This booking has been cancelled: :reason', ['reason' => $event->reason])
                    : __('This booking has been cancelled.'),
            ));
        }
    }
}
