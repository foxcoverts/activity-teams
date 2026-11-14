<?php

namespace App\Mail;

use App\Filament\Member\Pages\Logbook;
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

class BookingLogbookNudgeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Booking $booking,
        public User $member,
    ) {
        $this->afterCommit();
        $this->booking->loadMissing(['activity', 'venue', 'audience', 'team']);
    }

    public function envelope(): Envelope
    {
        return BookingMailThread::envelope(
            $this->booking,
            __('Logbook entry due'),
        );
    }

    public function headers(): Headers
    {
        return BookingMailThread::headers($this->booking);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking.logbook-nudge',
            with: [
                'booking' => $this->booking,
                'title' => __('Logbook entry due'),
                'intro' => __('Add this session to your personal logbook when you can.'),
                'logbookUrl' => Logbook::getUrl(panel: 'member'),
            ],
        );
    }
}
