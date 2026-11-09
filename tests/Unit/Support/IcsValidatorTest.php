<?php

namespace Tests\Unit\Support;

use App\Support\IcsValidator;
use Tests\TestCase;

class IcsValidatorTest extends TestCase
{
    public function test_accepts_a_minimal_valid_event(): void
    {
        $ics = $this->calendar([
            'BEGIN:VEVENT',
            'UID:booking+example@example.test',
            'DTSTAMP:20261109T220000Z',
            'DTSTART:20261114T093000Z',
            'DTEND:20261114T113000Z',
            'SUMMARY:Valid',
            'END:VEVENT',
        ]);

        $this->assertTrue(IcsValidator::isValid($ics));
        $this->assertSame([], IcsValidator::messages($ics));
        $this->assertSame([], IcsValidator::invalidMessages($ics));
    }

    public function test_rejects_offset_tzid_as_invalid(): void
    {
        $ics = $this->calendar([
            'BEGIN:VEVENT',
            'UID:booking+example@example.test',
            'DTSTAMP:20261109T220000Z',
            'DTSTART;TZID=+00:00:20261114T093000',
            'DTEND;TZID=+00:00:20261114T113000',
            'SUMMARY:Broken',
            'END:VEVENT',
        ]);

        $this->assertFalse(IcsValidator::isValid($ics));

        $messages = IcsValidator::messages($ics);

        $this->assertNotEmpty($messages);
        $this->assertTrue(
            collect($messages)->every(fn (array $warning): bool => ($warning['level'] ?? 0) >= 3),
        );

        $invalid = IcsValidator::invalidMessages($ics);

        $this->assertSame(
            collect($messages)->pluck('message')->all(),
            $invalid,
        );
        $this->assertTrue(
            collect($invalid)->contains(fn (string $message): bool => str_contains($message, 'DATE-TIME')),
        );
    }

    public function test_rejects_an_event_missing_required_uid(): void
    {
        $ics = $this->calendar([
            'BEGIN:VEVENT',
            'DTSTAMP:20261109T220000Z',
            'DTSTART:20261114T093000Z',
            'DTEND:20261114T113000Z',
            'SUMMARY:Missing UID',
            'END:VEVENT',
        ]);

        $this->assertFalse(IcsValidator::isValid($ics));
        $this->assertNotEmpty(IcsValidator::invalidMessages($ics));
    }

    /**
     * @param  list<string>  $lines
     */
    private function calendar(array $lines): string
    {
        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Activity Teams//EN',
            ...$lines,
            'END:VCALENDAR',
            '',
        ]);
    }
}
