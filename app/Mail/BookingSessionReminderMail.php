<?php

namespace App\Mail;

use App\Enums\CalendarMethod;
use App\Enums\MailGroup;
use App\Mail\Concerns\AttachesBookingCalendar;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Support\AttendanceResponseLinks;
use App\Support\BookingChangeSummary;
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

class BookingSessionReminderMail extends Mailable
{
    use AttachesBookingCalendar;
    use Queueable, SerializesModels;

    public ?BookingAttendance $attendance = null;

    public function __construct(
        public Booking $booking,
        public User $attendee,
    ) {
        $this->booking->loadMissing(['activity', 'venue', 'audience']);
        $this->attendance = $booking->attendees()->whereKey($attendee->getKey())->first()?->attendance;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: BookingMailThread::subject($this->booking, __('Reminder')),
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
        $token = $this->attendance?->token;

        return new Content(
            markdown: 'mail.booking.status',
            with: [
                'booking' => $this->booking,
                'title' => __('Coming up'),
                'intro' => __('You said you can help with this booking. It is coming up soon.'),
                'attendance' => $this->attendance,
                'changeList' => new BookingChangeSummary($this->booking, [], null),
                'changeNotice' => null,
                'leadResponsibilityNotice' => false,
                'leadNeededNotice' => false,
                'leadNotes' => null,
                'choiceUrls' => AttendanceResponseLinks::choices($this->booking, $this->attendee, $this->attendance),
                'respondUrl' => $token
                    ? route('respond', [
                        $this->booking,
                        $this->attendee,
                        'invite' => $token,
                        'sequence' => $this->booking->sequence,
                    ])
                    : null,
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
