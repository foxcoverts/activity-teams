<?php

namespace App\Support;

use App\Enums\CalendarMethod;
use Illuminate\Support\Collection;
use Sabre\VObject\Document;
use Sabre\VObject\Reader;

class InboundCalendar
{
    public function __construct(protected Document $vcalendar) {}

    public static function loadData(string $raw, string $charset = 'UTF-8'): static
    {
        return new static(
            Reader::read($raw, Reader::OPTION_FORGIVING, $charset)
        );
    }

    public function method(): ?CalendarMethod
    {
        $method = (string) ($this->vcalendar->METHOD ?? '');

        return CalendarMethod::tryFrom(strtoupper($method));
    }

    /**
     * @return Collection<int, InboundCalendarEvent>
     */
    public function events(): Collection
    {
        $events = [];

        foreach ($this->vcalendar->VEVENT ?? [] as $vevent) {
            $events[] = new InboundCalendarEvent($vevent);
        }

        return collect($events);
    }

    /**
     * @return Collection<int, InboundCalendarTodo>
     */
    public function todos(): Collection
    {
        $todos = [];

        foreach ($this->vcalendar->VTODO ?? [] as $vtodo) {
            $todos[] = new InboundCalendarTodo($vtodo);
        }

        return collect($todos);
    }
}
