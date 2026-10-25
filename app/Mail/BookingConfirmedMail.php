<?php

namespace App\Mail;

class BookingConfirmedMail extends BookingStatusMail
{
    protected function subjectKind(): string
    {
        return __('Confirmed');
    }

    protected function shouldAnnounceLeadResponsibility(): bool
    {
        return true;
    }

    protected function statusHeading(): string
    {
        return __('Booking confirmed');
    }

    protected function statusIntro(): string
    {
        return __('A booking has been confirmed.');
    }
}
