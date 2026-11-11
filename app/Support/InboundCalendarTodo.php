<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Sabre\VObject\Component\VToDo;

class InboundCalendarTodo
{
    public function __construct(protected VToDo $vtodo) {}

    public function uid(): string
    {
        return (string) $this->vtodo->UID;
    }

    public function task(): ?Task
    {
        return Task::findByUid($this->uid());
    }

    public function sequence(): ?int
    {
        if (! isset($this->vtodo->SEQUENCE)) {
            return null;
        }

        return (int) (string) $this->vtodo->SEQUENCE;
    }

    public function sentAt(): ?Carbon
    {
        if (! isset($this->vtodo->DTSTAMP)) {
            return null;
        }

        return Carbon::parse((string) $this->vtodo->DTSTAMP);
    }

    /**
     * @return Collection<int, InboundCalendarAttendee>
     */
    public function attendees(): Collection
    {
        $attendees = [];

        foreach ($this->vtodo->ATTENDEE ?? [] as $vattendee) {
            $attendees[] = new InboundCalendarAttendee($vattendee);
        }

        return collect($attendees);
    }
}
