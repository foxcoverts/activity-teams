<?php

namespace App\Mail;

use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingMailThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class BookingDebriefNudgeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Booking $booking,
        public User $lead,
        public bool $week = false,
    ) {
        $this->afterCommit();
        $this->booking->loadMissing(['activity', 'venue', 'audience', 'team']);
    }

    public function envelope(): Envelope
    {
        return BookingMailThread::envelope(
            $this->booking,
            $this->week ? __('Debrief still due') : __('Debrief due'),
        );
    }

    public function headers(): Headers
    {
        return BookingMailThread::headers($this->booking);
    }

    public function content(): Content
    {
        $team = $this->booking->team;

        return new Content(
            markdown: 'mail.booking.debrief-nudge',
            with: [
                'booking' => $this->booking,
                'week' => $this->week,
                'title' => $this->week ? __('Debrief still due') : __('Debrief due'),
                'intro' => $this->week
                    ? __('Please complete the debrief for this session when you can.')
                    : __('This session has finished. Please complete the debrief.'),
                'bookingUrl' => $team !== null
                    ? BookingResource::getUrl('view', ['record' => $this->booking], panel: 'teams', tenant: $team)
                    : null,
            ],
        );
    }
}
