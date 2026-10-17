<?php

namespace App\Mail;

class BookingChangedMail extends BookingStatusMail
{
    protected function heading(): string
    {
        return __('Booking updated');
    }

    protected function intro(): string
    {
        return __('A booking you are invited to has changed.');
    }

    protected function changeNotice(): ?string
    {
        return __('This booking has been updated');
    }
}
