<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Envelope;

class BookingChangedMail extends BookingStatusMail
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Booking updated'),
        );
    }

    protected function heading(): string
    {
        return __('Booking updated');
    }

    protected function intro(): string
    {
        return __('A booking you are invited to has changed.');
    }
}
