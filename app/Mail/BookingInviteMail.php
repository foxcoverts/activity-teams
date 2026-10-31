<?php

namespace App\Mail;

use App\Enums\CalendarMethod;
use App\Enums\MailGroup;
use App\Mail\Concerns\AttachesBookingCalendar;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Support\AttendanceResponseLinks;
use App\Support\BookingMailThread;
use App\Support\MailPreference;
use App\Support\MailReplyTo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class BookingInviteMail extends Mailable
{
    use AttachesBookingCalendar;
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
        return new Envelope(
            subject: BookingMailThread::subject($this->booking, __('Invitation')),
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
            markdown: 'mail.booking.invite',
            with: [
                'booking' => $this->booking,
                'attendee' => $this->attendee,
                'choiceUrls' => AttendanceResponseLinks::choices($this->booking, $this->attendee, $this->attendance),
                ...MailPreference::footerViewData($this->attendee, MailGroup::Bookings, $this->booking),
            ],
        );
    }

    protected function booking(): Booking
    {
        return $this->booking;
    }

    protected function attendee(): User
    {
        return $this->attendee;
    }

    protected function calendarMethod(): CalendarMethod
    {
        return CalendarMethod::Request;
    }
}
