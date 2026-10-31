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

class BookingStatusMail extends Mailable
{
    use AttachesBookingCalendar;
    use Queueable, SerializesModels;

    public ?BookingAttendance $attendance = null;

    /**
     * @param  list<string>  $changes
     * @param  array{start_at: mixed, end_at: mixed, timezone: mixed}|null  $previousSchedule
     */
    public function __construct(
        public Booking $booking,
        public User $attendee,
        public array $changes = [],
        public ?array $previousSchedule = null,
    ) {
        $this->booking->loadMissing(['activity', 'venue']);
        $this->attendance = $booking->attendees()->whereKey($attendee->getKey())->first()?->attendance;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: BookingMailThread::subject($this->booking, $this->subjectKind()),
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

    protected function subjectKind(): string
    {
        return __('Updated');
    }

    public function content(): Content
    {
        $token = $this->attendance?->token;
        $changes = new BookingChangeSummary($this->booking, $this->changes, $this->previousSchedule);

        return new Content(
            markdown: 'mail.booking.status',
            with: [
                'booking' => $this->booking,
                'title' => $this->heading(),
                'intro' => $this->intro(),
                'attendance' => $this->attendance,
                'changeList' => $changes,
                'changeNotice' => $this->usesSpecialPanel() ? null : ($changes->summary() ? $this->changeNotice() : null),
                'leadResponsibilityNotice' => $this->announcesLeadResponsibility(),
                'leadNeededNotice' => $this->announcesLeadNeeded(),
                'leadNotes' => $this->leadNotesForRecipient(),
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

    protected function changeNotice(): ?string
    {
        return null;
    }

    protected function heading(): string
    {
        return $this->statusHeading();
    }

    protected function intro(): string
    {
        return $this->statusIntro();
    }

    protected function statusHeading(): string
    {
        return __('Booking updated');
    }

    protected function statusIntro(): string
    {
        return __('A booking you are invited to has changed.');
    }

    protected function usesSpecialPanel(): bool
    {
        return $this->announcesLeadResponsibility() || $this->announcesLeadNeeded();
    }

    protected function announcesLeadResponsibility(): bool
    {
        return $this->isLeadRecipient() && $this->shouldAnnounceLeadResponsibility();
    }

    protected function announcesLeadNeeded(): bool
    {
        return false;
    }

    protected function shouldAnnounceLeadResponsibility(): bool
    {
        return in_array('lead_instructor_id', $this->changes, true);
    }

    protected function isLeadRecipient(): bool
    {
        return $this->booking->lead_instructor_id === $this->attendee->getKey();
    }

    protected function leadNotesForRecipient(): ?string
    {
        if (! $this->isLeadRecipient()) {
            return null;
        }

        $notes = $this->booking->lead_instructor_notes;

        return filled($notes) ? $notes : null;
    }
}
