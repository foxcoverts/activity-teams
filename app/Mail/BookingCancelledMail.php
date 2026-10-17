<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Mail\Mailables\Envelope;

class BookingCancelledMail extends BookingStatusMail
{
    public function __construct(
        Booking $booking,
        User $attendee,
        public ?string $reason = null,
    ) {
        parent::__construct($booking, $attendee);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Booking cancelled'),
        );
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
}
