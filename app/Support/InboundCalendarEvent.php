<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Sabre\VObject\Component\VEvent;

class InboundCalendarEvent
{
    public function __construct(protected VEvent $vevent) {}

    public function uid(): string
    {
        return (string) $this->vevent->UID;
    }

    public function booking(): ?Booking
    {
        return Booking::findByUid($this->uid());
    }

    public function sequence(): ?int
    {
        if (! isset($this->vevent->SEQUENCE)) {
            return null;
        }

        return (int) (string) $this->vevent->SEQUENCE;
    }

    public function sentAt(): ?Carbon
    {
        if (! isset($this->vevent->DTSTAMP)) {
            return null;
        }

        return Carbon::parse((string) $this->vevent->DTSTAMP);
    }

    /**
     * @return Collection<int, InboundCalendarAttendee>
     */
    public function attendees(): Collection
    {
        $attendees = [];

        foreach ($this->vevent->ATTENDEE ?? [] as $vattendee) {
            $attendees[] = new InboundCalendarAttendee($vattendee);
        }

        return collect($attendees);
    }
}
