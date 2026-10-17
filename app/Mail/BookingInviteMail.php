<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?BookingAttendance $attendance = null;

    public function __construct(
        public Booking $booking,
        public User $attendee
    ) {
        $this->attendance = $booking->attendees()->find($attendee)?->attendance;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You have been invited to a booking'),
        );
    }

    public function content(): Content
    {
        $token = $this->attendance?->token;

        return new Content(
            markdown: 'mail.booking.invite',
            with: [
                'booking' => $this->booking,
                'attendee' => $this->attendee,
                'respondUrl' => $token
                    ? route('respond', [$this->booking, $this->attendee, 'invite' => $token])
                    : url('/'),
            ],
        );
    }
}
