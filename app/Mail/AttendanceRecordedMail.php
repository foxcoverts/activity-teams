<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Support\AttendanceResponseLinks;
use App\Support\BookingMailThread;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class AttendanceRecordedMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?BookingAttendance $attendance = null;

    public function __construct(
        public Booking $booking,
        public User $attendee,
    ) {
        $this->booking->loadMissing(['activity', 'venue']);
        $this->attendance = $booking->attendees()->whereKey($attendee->getKey())->first()?->attendance;
    }

    public function envelope(): Envelope
    {
        return BookingMailThread::envelope($this->booking, __('Attendance'));
    }

    public function headers(): Headers
    {
        return BookingMailThread::headers($this->booking);
    }

    public function content(): Content
    {
        $token = $this->attendance?->token;

        return new Content(
            markdown: 'mail.booking.attendance-recorded',
            with: [
                'booking' => $this->booking,
                'attendance' => $this->attendance,
                'choiceUrls' => AttendanceResponseLinks::choices($this->booking, $this->attendee, $this->attendance),
                'respondUrl' => $token
                    ? route('respond', [$this->booking, $this->attendee, 'invite' => $token])
                    : null,
            ],
        );
    }
}
