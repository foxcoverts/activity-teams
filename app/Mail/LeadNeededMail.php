<?php

namespace App\Mail;

use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Support\BookingMailThread;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class LeadNeededMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?User $previousLead,
    ) {
        $this->booking->loadMissing(['activity', 'venue', 'team']);
    }

    public function envelope(): Envelope
    {
        return BookingMailThread::envelope($this->booking, __('Lead needed'));
    }

    public function headers(): Headers
    {
        return BookingMailThread::headers($this->booking);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking.lead-needed',
            with: [
                'booking' => $this->booking,
                'previousLead' => $this->previousLead,
                'bookingUrl' => $this->manageUrl(),
            ],
        );
    }

    private function manageUrl(): ?string
    {
        $team = $this->booking->team;

        if (! $team instanceof Team) {
            return null;
        }

        return BookingResource::getUrl('view', ['record' => $this->booking], panel: 'manage', tenant: $team);
    }
}
