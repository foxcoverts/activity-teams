<?php

namespace App\Mail\Concerns;

use App\Enums\BookingAttendeeStatus;
use App\Enums\CalendarMethod;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingIcsFactory;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

trait AttachesBookingCalendar
{
    abstract protected function booking(): Booking;

    abstract protected function attendee(): User;

    abstract protected function calendarMethod(): CalendarMethod;

    protected function attachCalendarData(Email $message): void
    {
        $booking = $this->booking();
        $attendee = $this->attendee();
        $method = $this->calendarMethod();

        $ics = app(BookingIcsFactory::class)->make(
            [$booking],
            $booking->activity->name,
            method: $method,
            forAttendee: $attendee,
            rsvp: $this->calendarNeedsRsvp(),
        );

        $inline = new DataPart($ics, filename: 'invite', contentType: 'text/calendar');
        $inline->asInline();
        $inline->getHeaders()->addParameterizedHeader('Content-Type', 'text/calendar', [
            'method' => $method->value,
            'charset' => 'utf-8',
            'component' => 'vevent',
        ]);
        $message->addPart($inline);

        $download = new DataPart($ics, filename: 'invite.ics', contentType: 'text/calendar');
        $download->getHeaders()->addParameterizedHeader('Content-Type', 'text/calendar', [
            'method' => $method->value,
            'charset' => 'utf-8',
            'component' => 'vevent',
            'name' => 'invite.ics',
        ]);
        $message->addPart($download);
    }

    protected function calendarNeedsRsvp(): bool
    {
        $attendance = $this->booking()->attendees()->whereKey($this->attendee()->getKey())->first()?->attendance;

        return match ($attendance?->status) {
            BookingAttendeeStatus::NeedsAction, BookingAttendeeStatus::Tentative => true,
            BookingAttendeeStatus::Accepted => true,
            default => false,
        };
    }
}
