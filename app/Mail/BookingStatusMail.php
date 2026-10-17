<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public User $attendee,
        public string $subject,
        public string $intro,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking.status',
            with: [
                'booking' => $this->booking,
                'attendee' => $this->attendee,
                'intro' => $this->intro,
                'url' => url('/bookings/'.$this->booking->id),
            ],
        );
    }
}
