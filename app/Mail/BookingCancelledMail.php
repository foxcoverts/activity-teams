<?php

namespace App\Mail;

use App\Enums\CalendarMethod;
use App\Models\Booking;
use App\Models\User;

class BookingCancelledMail extends BookingStatusMail
{
    public function __construct(
        Booking $booking,
        User $attendee,
        public ?string $reason = null,
    ) {
        parent::__construct($booking, $attendee);
    }

    protected function subjectKind(): string
    {
        return __('Cancelled');
    }

    protected function heading(): string
    {
        return __('Booking cancelled');
    }

    protected function intro(): string
    {
        return $this->reason
            ? __('This booking has been cancelled: :reason', ['reason' => $this->reason])
            : __('This booking has been cancelled.');
    }

    protected function shouldAnnounceLeadResponsibility(): bool
    {
        return false;
    }

    protected function leadNotesForRecipient(): ?string
    {
        return null;
    }

    protected function calendarMethod(): CalendarMethod
    {
        return CalendarMethod::Cancel;
    }

    protected function calendarNeedsRsvp(): bool
    {
        return false;
    }
}
