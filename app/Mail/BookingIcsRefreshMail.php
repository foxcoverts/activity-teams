<?php

namespace App\Mail;

use App\Enums\CalendarMethod;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class BookingIcsRefreshMail extends BookingStatusMail implements ShouldQueue
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        Booking $booking,
        User $attendee,
        public CalendarMethod $method = CalendarMethod::Request,
    ) {
        parent::__construct($booking, $attendee);
        $this->afterCommit();
    }

    protected function subjectKind(): string
    {
        return $this->method === CalendarMethod::Cancel
            ? __('Cancelled')
            : __('Updated');
    }

    protected function calendarMethod(): CalendarMethod
    {
        return $this->method;
    }

    protected function calendarNeedsRsvp(): bool
    {
        if ($this->method === CalendarMethod::Cancel) {
            return false;
        }

        return parent::calendarNeedsRsvp();
    }

    protected function heading(): string
    {
        return $this->method === CalendarMethod::Cancel
            ? __('Booking cancelled')
            : __('Booking details');
    }

    protected function intro(): string
    {
        return $this->method === CalendarMethod::Cancel
            ? __('Here are the current details for this cancelled booking.')
            : __('Here are the current details for this booking.');
    }

    protected function shouldAnnounceLeadResponsibility(): bool
    {
        return false;
    }
}
