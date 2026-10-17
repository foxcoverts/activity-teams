<?php

namespace App\Mail;

class BookingConfirmedMail extends BookingStatusMail
{
    protected function subjectKind(): string
    {
        return __('Confirmed');
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
