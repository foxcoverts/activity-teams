<?php

namespace App\Mail;

use App\Enums\CalendarMethod;
use App\Enums\MailGroup;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingMailThread;
use App\Support\MailPreference;
use App\Support\MailReplyTo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class BookingIcsRefreshMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Booking $booking,
        public User $attendee,
        public string $ics,
        public CalendarMethod $method,
    ) {
        $this->afterCommit();
        $this->booking->loadMissing(['activity', 'venue']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: BookingMailThread::subject(
                $this->booking,
                $this->method === CalendarMethod::Cancel ? __('Cancelled') : __('Updated'),
            ),
            replyTo: [MailReplyTo::booking($this->booking)],
            using: [
                fn (Email $message) => $this->attachCalendarData($message),
            ],
        );
    }

    public function headers(): Headers
    {
        return BookingMailThread::headers($this->booking);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking.ics-refresh',
            with: [
                'booking' => $this->booking,
                'attendee' => $this->attendee,
                'cancelled' => $this->method === CalendarMethod::Cancel,
                ...MailPreference::footerViewData($this->attendee, MailGroup::Bookings, $this->booking),
            ],
        );
    }

    protected function attachCalendarData(Email $message): void
    {
        $method = $this->method->value;

        $inline = new DataPart($this->ics, filename: 'invite', contentType: 'text/calendar');
        $inline->asInline();
        $inline->getHeaders()->addParameterizedHeader('Content-Type', 'text/calendar', [
            'method' => $method,
            'charset' => 'utf-8',
            'component' => 'vevent',
        ]);
        $message->addPart($inline);

        $download = new DataPart($this->ics, filename: 'invite.ics', contentType: 'text/calendar');
        $download->getHeaders()->addParameterizedHeader('Content-Type', 'text/calendar', [
            'method' => $method,
            'charset' => 'utf-8',
            'component' => 'vevent',
            'name' => 'invite.ics',
        ]);
        $message->addPart($download);
    }
}
