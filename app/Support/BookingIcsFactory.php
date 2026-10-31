<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Enums\CalendarMethod;
use App\Models\Booking;
use App\Models\User;
use DateTimeImmutable;
use Eluceo\iCal\Domain\Entity\Attendee;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\Enum\EventStatus;
use Eluceo\iCal\Domain\Enum\ParticipationStatus;
use Eluceo\iCal\Domain\Enum\RoleType;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\EmailAddress;
use Eluceo\iCal\Domain\ValueObject\GeographicPosition;
use Eluceo\iCal\Domain\ValueObject\Organizer;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Component;
use Eluceo\iCal\Presentation\Component\Property;
use Eluceo\iCal\Presentation\Component\Property\Parameter;
use Eluceo\iCal\Presentation\Component\Property\Value\AppleLocationGeoValue;
use Eluceo\iCal\Presentation\Component\Property\Value\GeoValue;
use Eluceo\iCal\Presentation\Component\Property\Value\IntegerValue;
use Eluceo\iCal\Presentation\Component\Property\Value\TextValue;
use Eluceo\iCal\Presentation\Component\Property\Value\UriValue;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Eluceo\iCal\Presentation\Factory\EventFactory;
use Generator;

class BookingIcsFactory
{
    public function make(
        iterable $bookings,
        string $title,
        ?CalendarMethod $method = null,
        ?User $forAttendee = null,
        bool $rsvp = false,
    ): string {
        $calendar = new Calendar;
        $calendar->setProductIdentifier('-//Activity Teams//EN');
        $calendar->setCalName($title);

        /** @var array<string, array{related: list<string>, sequence: int, location: ?Location, coords: ?Coords}> $metaByUid */
        $metaByUid = [];

        foreach ($bookings as $booking) {
            if (! $booking instanceof Booking || ! $booking->start_at || ! $booking->end_at) {
                continue;
            }

            $booking->loadMissing(['activity', 'venue', 'related', 'attendees']);

            $start = new DateTime(new DateTimeImmutable($booking->start_at->toIso8601String()), true);
            $end = new DateTime(new DateTimeImmutable($booking->end_at->toIso8601String()), true);

            $event = (new Event(new UniqueIdentifier($booking->uid)))
                ->setSummary($booking->activity->name.' — '.$booking->group_name)
                ->setDescription($booking->notes ?? '')
                ->setOccurrence(new TimeSpan($start, $end));

            $event = match ($booking->status->value) {
                'confirmed' => $event->setStatus(EventStatus::CONFIRMED()),
                'cancelled' => $event->setStatus(EventStatus::CANCELLED()),
                default => $event->setStatus(EventStatus::TENTATIVE()),
            };

            if ($method === CalendarMethod::Request || $method === CalendarMethod::Cancel) {
                $event->setOrganizer(new Organizer(
                    new EmailAddress(InboundMailAddress::forBooking($booking)),
                    config('mail.from.name'),
                ));

                if ($forAttendee instanceof User && filled($forAttendee->email)) {
                    $attendee = (new Attendee(new EmailAddress($forAttendee->email)))
                        ->setDisplayName($forAttendee->name)
                        ->setResponseNeededFromAttendee($rsvp && $method === CalendarMethod::Request)
                        ->setRole(
                            $booking->lead_instructor_id === $forAttendee->getKey()
                                ? RoleType::CHAIR()
                                : RoleType::REQ_PARTICIPANT()
                        );

                    $status = $booking->attendees->find($forAttendee->getKey())?->attendance?->status;

                    if ($status instanceof BookingAttendeeStatus) {
                        $participation = match ($status) {
                            BookingAttendeeStatus::Accepted => ParticipationStatus::ACCEPTED(),
                            BookingAttendeeStatus::Declined => ParticipationStatus::DECLINED(),
                            BookingAttendeeStatus::Tentative => ParticipationStatus::TENTATIVE(),
                            default => null,
                        };

                        if ($participation instanceof ParticipationStatus) {
                            $attendee->setParticipationStatus($participation);
                        }
                    }

                    $event->addAttendee($attendee);
                }
            }

            $metaByUid[$booking->uid] = [
                'related' => $booking->related
                    ->map(fn (Booking $related): string => $related->uid)
                    ->all(),
                'sequence' => (int) $booking->sequence,
                'location' => $booking->location,
                'coords' => $booking->coords,
            ];

            $calendar->addEvent($event);
        }

        $eventFactory = new class($metaByUid) extends EventFactory
        {
            /**
             * @param  array<string, array{related: list<string>, sequence: int, location: ?Location, coords: ?Coords}>  $metaByUid
             */
            public function __construct(private array $metaByUid)
            {
                parent::__construct();
            }

            public function createComponent(Event $event): Component
            {
                $component = parent::createComponent($event)
                    ->withProperty(new Property('CLASS', new TextValue('PRIVATE')));

                $uid = (string) $event->getUniqueIdentifier();
                $meta = $this->metaByUid[$uid] ?? null;

                if ($meta === null) {
                    return $component;
                }

                $component = $component->withProperty(
                    new Property('SEQUENCE', new IntegerValue($meta['sequence'])),
                );

                foreach ($meta['related'] as $relatedUid) {
                    $component = $component->withProperty(new Property(
                        'RELATED-TO',
                        new TextValue($relatedUid),
                        [new Parameter('RELTYPE', new TextValue('SIBLING'))],
                    ));
                }

                foreach ($this->locationProperties($meta['location'], $meta['coords']) as $property) {
                    $component = $component->withProperty($property);
                }

                return $component;
            }

            protected function getComponents(Event $event): Generator
            {
                yield from parent::getComponents($event);

                $uid = (string) $event->getUniqueIdentifier();
                $meta = $this->metaByUid[$uid] ?? null;

                if ($meta === null) {
                    return;
                }

                $vlocation = $this->vlocationComponent($meta['location'], $meta['coords']);

                if ($vlocation instanceof Component) {
                    yield $vlocation;
                }
            }

            /**
             * @return Generator<Property>
             */
            private function locationProperties(?Location $location, ?Coords $coords): Generator
            {
                if ($location === null) {
                    return;
                }

                $text = BookingIcsFactory::locationText($location);
                $parameters = [];

                if ($coords instanceof Coords) {
                    $parameters[] = new Parameter(
                        'ALTREP',
                        new UriValue(new Uri($this->openStreetMapUri($coords))),
                    );
                }

                yield new Property('LOCATION', new TextValue($text), $parameters);

                if ($coords instanceof Coords) {
                    $geo = new GeographicPosition($coords->latitude, $coords->longitude);

                    yield new Property('GEO', new GeoValue($geo));
                    yield new Property(
                        'X-APPLE-STRUCTURED-LOCATION',
                        new AppleLocationGeoValue($geo),
                        [
                            new Parameter('VALUE', new TextValue('URI')),
                            new Parameter('X-ADDRESS', new TextValue($text)),
                            new Parameter('X-APPLE-RADIUS', new IntegerValue(49)),
                            new Parameter('X-TITLE', new TextValue($location->name)),
                        ],
                    );
                }

                $street = trim(implode(', ', array_filter([$location->street, $location->street2])));

                foreach ([
                    'X-MICROSOFT-LOCATIONSTREET' => $street !== '' ? $street : null,
                    'X-MICROSOFT-LOCATIONCITY' => $location->city,
                    'X-MICROSOFT-LOCATIONSTATE' => $location->region,
                    'X-MICROSOFT-LOCATIONPOSTALCODE' => $location->postcode,
                    'X-MICROSOFT-LOCATIONCOUNTRY' => $location->countryName(),
                ] as $name => $value) {
                    if (filled($value)) {
                        yield new Property($name, new TextValue((string) $value));
                    }
                }

                if ($coords instanceof Coords) {
                    yield new Property('X-MICROSOFT-LOCATIONLATITUDE', new TextValue((string) $coords->latitude));
                    yield new Property('X-MICROSOFT-LOCATIONLONGITUDE', new TextValue((string) $coords->longitude));
                }
            }

            private function vlocationComponent(?Location $location, ?Coords $coords): ?Component
            {
                if ($location === null) {
                    return null;
                }

                $addressLines = BookingIcsFactory::icsAddressLines($location);
                $hasAddress = $addressLines !== [];
                $hasCoords = $coords instanceof Coords;

                if (! $hasAddress && ! $hasCoords) {
                    return null;
                }

                $properties = [
                    new Property('NAME', new TextValue($location->name)),
                ];

                if ($hasCoords) {
                    $properties[] = new Property(
                        'GEO',
                        new GeoValue(new GeographicPosition($coords->latitude, $coords->longitude)),
                    );
                }

                if ($hasAddress) {
                    $properties[] = new Property('ADDRESS', new TextValue(implode("\n", $addressLines)));
                }

                return new Component('VLOCATION', $properties);
            }

            private function openStreetMapUri(Coords $coords): string
            {
                return sprintf(
                    'https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=18/%s/%s',
                    $coords->latitude,
                    $coords->longitude,
                    $coords->latitude,
                    $coords->longitude,
                );
            }
        };

        $calendarFactory = new class($method, $eventFactory) extends CalendarFactory
        {
            public function __construct(private ?CalendarMethod $method, EventFactory $eventFactory)
            {
                parent::__construct($eventFactory);
            }

            public function createCalendar(Calendar $calendar): Component
            {
                $component = parent::createCalendar($calendar);

                if ($this->method instanceof CalendarMethod) {
                    $component = $component->withProperty(
                        new Property('METHOD', new TextValue($this->method->value)),
                    );
                }

                return $component;
            }
        };

        return $calendarFactory->createCalendar($calendar)->__toString();
    }

    public static function locationText(?Location $location): string
    {
        if ($location === null) {
            return '';
        }

        $lines = self::icsAddressLines($location);

        return $lines === [] ? $location->name : implode("\n", $lines);
    }

    /**
     * Postal lines for ICS LOCATION / VLOCATION ADDRESS. Omits the country name
     * when the code is GB so LOCATION matches UK postal order. Views keep
     * addressArray() unchanged.
     *
     * @return list<string>
     */
    public static function icsAddressLines(Location $location): array
    {
        $lines = array_values(array_filter([
            $location->street,
            $location->street2,
            $location->city,
            $location->region,
            $location->postcode,
        ], fn (?string $value): bool => filled($value)));

        if (filled($location->country) && strtoupper($location->country) !== 'GB') {
            $country = $location->countryName();

            if (filled($country)) {
                $lines[] = $country;
            }
        }

        return $lines;
    }
}
