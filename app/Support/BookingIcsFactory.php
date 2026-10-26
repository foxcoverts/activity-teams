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
use Eluceo\iCal\Presentation\Component;
use Eluceo\iCal\Presentation\Component\Property;
use Eluceo\iCal\Presentation\Component\Property\Parameter;
use Eluceo\iCal\Presentation\Component\Property\Value\TextValue;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Eluceo\iCal\Presentation\Factory\EventFactory;

class BookingIcsFactory
{
    public function make(iterable $bookings, string $title): string
    {
        $calendar = new Calendar;
        $calendar->setProductIdentifier('-//Activity Teams//EN');

        /** @var array<string, list<string>> $relatedByUid */
        $relatedByUid = [];

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

            $booking->loadMissing('related');
            $relatedByUid[$booking->uid] = $booking->related
                ->map(fn (Booking $related): string => $related->uid)
                ->all();

            unset($timezone);
            $calendar->addEvent($event);
        }

        unset($title);

        $eventFactory = new class($relatedByUid) extends EventFactory
        {
            /**
             * @param  array<string, list<string>>  $relatedByUid
             */
            public function __construct(private array $relatedByUid)
            {
                parent::__construct();
            }

            public function createComponent(Event $event): Component
            {
                $component = parent::createComponent($event)
                    ->withProperty(new Property('CLASS', new TextValue('PRIVATE')));

                $uid = (string) $event->getUniqueIdentifier();

                foreach ($this->relatedByUid[$uid] ?? [] as $relatedUid) {
                    $component = $component->withProperty(new Property(
                        'RELATED-TO',
                        new TextValue($relatedUid),
                        [new Parameter('RELTYPE', new TextValue('SIBLING'))],
                    ));
                }

                return $component;
            }
        };

        return (new CalendarFactory($eventFactory))->createCalendar($calendar)->__toString();
    }
}
