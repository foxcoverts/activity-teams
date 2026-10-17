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

class BookingStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?BookingAttendance $attendance = null;

    public function __construct(
        public Booking $booking,
        public User $attendee,
    ) {
        $this->booking->loadMissing(['activity', 'venue']);
        $this->attendance = $booking->attendees()->find($attendee)?->attendance;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->heading(),
        );
    }

    public function content(): Content
    {
        $token = $this->attendance?->token;

        return new Content(
            markdown: 'mail.booking.status',
            with: [
                'booking' => $this->booking,
                'title' => $this->heading(),
                'intro' => $this->intro(),
                'attendance' => $this->attendance,
                'respondUrl' => $token
                    ? route('respond', [$this->booking, $this->attendee, 'invite' => $token])
                    : null,
            ],
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
