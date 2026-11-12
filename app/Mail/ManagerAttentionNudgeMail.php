<?php

namespace App\Mail;

use App\Enums\TentativeTriage;
use App\Filament\Teams\Pages\ManagerDashboard;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Team;
use App\Support\BookingMailThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ManagerAttentionNudgeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Booking $booking,
        public TentativeTriage $triage,
        public Team $team,
    ) {
        $this->afterCommit();
        $this->booking->loadMissing(['activity', 'venue', 'audience', 'team']);
    }

    public function envelope(): Envelope
    {
        return BookingMailThread::envelope($this->booking, $this->triage->getLabel());
    }

    public function headers(): Headers
    {
        return BookingMailThread::headers($this->booking);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking.manager-attention',
            with: [
                'booking' => $this->booking,
                'triage' => $this->triage,
                'bookingUrl' => BookingResource::getUrl('view', ['record' => $this->booking], panel: 'teams', tenant: $this->team),
                'managerUrl' => ManagerDashboard::getUrl(panel: 'teams', tenant: $this->team),
            ],
        );
    }
}
