<?php

namespace App\Support;

use App\Models\Booking;
use DateTimeImmutable;
use DateTimeZone;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\Enum\EventStatus;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;

class BookingIcsFactory
{
    public function make(iterable $bookings, string $title): string
    {
        $calendar = new Calendar;
        $calendar->setProductIdentifier('-//Activity Teams//EN');

        foreach ($bookings as $booking) {
            if (! $booking instanceof Booking || ! $booking->start_at || ! $booking->end_at) {
                continue;
            }

            $timezone = new DateTimeZone($booking->timezone?->getName() ?? 'UTC');
            $start = new DateTime(new DateTimeImmutable($booking->start_at->toIso8601String()), true);
            $end = new DateTime(new DateTimeImmutable($booking->end_at->toIso8601String()), true);

            $event = (new Event(new UniqueIdentifier($booking->uid)))
                ->setSummary($booking->activity->name.' — '.$booking->group_name)
                ->setDescription($booking->notes ?? '')
                ->setLocation(new Location($booking->venue->name))
                ->setOccurrence(new TimeSpan($start, $end));

            $event = match ($booking->status->value) {
                'confirmed' => $event->setStatus(EventStatus::CONFIRMED()),
                'cancelled' => $event->setStatus(EventStatus::CANCELLED()),
                default => $event->setStatus(EventStatus::TENTATIVE()),
            };

            unset($timezone);
            $calendar->addEvent($event);
        }

        unset($title);

        return (new CalendarFactory)->createCalendar($calendar)->__toString();
    }
}
