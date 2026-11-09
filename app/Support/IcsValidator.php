<?php

namespace App\Support;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class IcsValidator
{
    /**
     * Parse ICS and return sabre/vobject validation messages.
     *
     * Level 3 = invalid, 2 = interoperability warning, 1 = repaired.
     *
     * @return list<array{level: int, message: string, node: mixed}>
     */
    public static function messages(string $ics): array
    {
        $calendar = Reader::read($ics);

        if (! $calendar instanceof VCalendar) {
            return [[
                'level' => 3,
                'message' => 'ICS did not parse as a VCALENDAR.',
                'node' => $calendar,
            ]];
        }

        return array_values($calendar->validate());
    }

    /**
     * @return list<string>
     */
    public static function invalidMessages(string $ics): array
    {
        return array_values(array_map(
            fn (array $warning): string => (string) $warning['message'],
            array_filter(
                self::messages($ics),
                fn (array $warning): bool => ($warning['level'] ?? 0) >= 3,
            ),
        ));
    }

    public static function isValid(string $ics): bool
    {
        return self::invalidMessages($ics) === [];
    }
}
