<?php

namespace App\Mail;

use App\Enums\MailGroup;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Support\AttendanceResponseLinks;
use App\Support\BookingMailThread;
use App\Support\MailPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class BookingInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?BookingAttendance $attendance = null;

    public function __construct(
        public Booking $booking,
        public User $attendee
    ) {
        $this->attendance = $booking->attendees()->whereKey($attendee->getKey())->first()?->attendance;
    }

    public function envelope(): Envelope
    {
        return BookingMailThread::envelope($this->booking, __('Invitation'));
    }

    public function headers(): Headers
    {
        return BookingMailThread::headers($this->booking);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.booking.invite',
            with: [
                'booking' => $this->booking,
                'attendee' => $this->attendee,
                'choiceUrls' => AttendanceResponseLinks::choices($this->booking, $this->attendee, $this->attendance),
                ...MailPreference::footerViewData($this->attendee, MailGroup::Bookings, $this->booking),
            ],
        );
    }
}
