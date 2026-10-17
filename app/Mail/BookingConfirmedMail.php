<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Envelope;

class BookingConfirmedMail extends BookingStatusMail
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Booking confirmed'),
        );
    }

    protected function heading(): string
    {
        return __('Booking confirmed');
    }

    protected function intro(): string
    {
        return __('A booking has been confirmed.');
    }
}
