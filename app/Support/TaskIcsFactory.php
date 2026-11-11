<?php

namespace App\Support;

use App\Enums\CalendarMethod;
use App\Enums\TaskAttendeeStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone as PhpDateTimeZone;
use Eluceo\iCal\Domain\ValueObject\DateTime as IcsDateTime;
use Eluceo\iCal\Domain\ValueObject\EmailAddress;
use Eluceo\iCal\Domain\ValueObject\Timestamp;
use Eluceo\iCal\Presentation\Component;
use Eluceo\iCal\Presentation\Component\Property;
use Eluceo\iCal\Presentation\Component\Property\Parameter;
use Eluceo\iCal\Presentation\Component\Property\Value\DateTimeValue;
use Eluceo\iCal\Presentation\Component\Property\Value\DurationValue;
use Eluceo\iCal\Presentation\Component\Property\Value\IntegerValue;
use Eluceo\iCal\Presentation\Component\Property\Value\TextValue;
use Eluceo\iCal\Presentation\Component\Property\Value\UriValue;
use Illuminate\Support\Str;

class TaskIcsFactory
{
    /**
     * @param  iterable<int, Task>  $tasks
     */
    public function make(
        iterable $tasks,
        string $title,
        ?CalendarMethod $method = null,
        ?User $forAssignee = null,
        bool $rsvp = false,
        ?DateInterval $publishedTtl = null,
    ): string {
        $properties = [
            new Property('PRODID', new TextValue('-//Activity Teams//EN')),
            new Property('VERSION', new TextValue('2.0')),
            new Property('CALSCALE', new TextValue('GREGORIAN')),
            new Property('X-WR-CALNAME', new TextValue($title)),
        ];

        if ($method instanceof CalendarMethod) {
            $properties[] = new Property('METHOD', new TextValue($method->value));
        }

        if ($publishedTtl instanceof DateInterval) {
            $properties[] = new Property('X-PUBLISHED-TTL', new DurationValue($publishedTtl));
        }

        $components = [];

        foreach ($tasks as $task) {
            if (! $task instanceof Task) {
                continue;
            }

            $task->loadMissing(['bookings', 'venue', 'attendance']);
            $components[] = $this->todoComponent($task, $forAssignee, $rsvp);
        }

        return (string) new Component('VCALENDAR', $properties, $components);
    }

    /**
     * @param  iterable<int, Task>  $tasks
     * @return list<Component>
     */
    public function components(iterable $tasks, ?User $forAssignee = null): array
    {
        $components = [];

        foreach ($tasks as $task) {
            if (! $task instanceof Task) {
                continue;
            }

            $task->loadMissing(['bookings', 'venue', 'attendance']);
            $components[] = $this->todoComponent($task, $forAssignee, rsvp: false);
        }

        return $components;
    }

    private function todoComponent(Task $task, ?User $forAssignee = null, bool $rsvp = false): Component
    {
        $properties = [
            new Property('UID', new TextValue($task->uid)),
            new Property('DTSTAMP', new DateTimeValue(new Timestamp(new DateTimeImmutable('now', new PhpDateTimeZone('UTC'))))),
            new Property('SUMMARY', new TextValue($task->summary)),
            new Property('SEQUENCE', new IntegerValue((int) $task->sequence)),
            new Property('STATUS', new TextValue(match ($task->status) {
                TaskStatus::NeedsAction => 'NEEDS-ACTION',
                TaskStatus::InProcess => 'IN-PROCESS',
                TaskStatus::Completed => 'COMPLETED',
                TaskStatus::Cancelled => 'CANCELLED',
            })),
            new Property('PRIORITY', new IntegerValue($task->priority->value)),
            new Property('CLASS', new TextValue('PRIVATE')),
        ];

        $fromName = (string) config('mail.from.name');

        $properties[] = new Property(
            'ORGANIZER',
            new UriValue((new EmailAddress(InboundMailAddress::forTask($task)))->toUri()),
            filled($fromName) ? [new Parameter('CN', new TextValue($fromName))] : [],
        );

        if ($task->updated_at !== null) {
            $properties[] = new Property(
                'LAST-MODIFIED',
                new DateTimeValue(new Timestamp($task->updated_at->toDateTimeImmutable())),
            );
        }

        [$textDescription, $htmlDescription] = $this->descriptions($task);

        if (filled($textDescription)) {
            $properties[] = new Property('DESCRIPTION', new TextValue($textDescription));
            $properties[] = new Property('X-ALT-DESC', new TextValue($htmlDescription), [
                new Parameter('FMTTYPE', new TextValue('text/html')),
            ]);
        }

        if ($task->location !== null && filled($task->location->name)) {
            $properties[] = new Property('LOCATION', new TextValue($task->location->name));
        }

        if ($task->due_at !== null) {
            $properties[] = new Property(
                'DUE',
                new DateTimeValue(new IcsDateTime($task->due_at->toDateTimeImmutable(), true)),
            );
        }

        if ($task->started_at !== null) {
            $properties[] = new Property(
                'DTSTART',
                new DateTimeValue(new IcsDateTime($task->started_at->toDateTimeImmutable(), true)),
            );
        }

        if ($task->completed_at !== null) {
            $properties[] = new Property(
                'COMPLETED',
                new DateTimeValue(new IcsDateTime($task->completed_at->toDateTimeImmutable(), true)),
            );
        }

        foreach ($task->bookings as $booking) {
            $properties[] = new Property(
                'RELATED-TO',
                new TextValue($booking->uid),
                [new Parameter('RELTYPE', new TextValue('PARENT'))],
            );
        }

        $assignee = $this->resolveAssignee($task, $forAssignee);

        if ($assignee instanceof User && filled($assignee->email)) {
            $attendance = $task->attendance;
            $partstat = $attendance?->status ?? TaskAttendeeStatus::NeedsAction;

            $attendeeParams = [
                new Parameter('CN', new TextValue($assignee->name)),
                new Parameter('PARTSTAT', new TextValue($partstat->toPartstat())),
                new Parameter('ROLE', new TextValue('REQ-PARTICIPANT')),
            ];

            if ($rsvp) {
                $attendeeParams[] = new Parameter('RSVP', new TextValue('TRUE'));
            }

            $properties[] = new Property(
                'ATTENDEE',
                new UriValue((new EmailAddress($assignee->email))->toUri()),
                $attendeeParams,
            );
        }

        return new Component('VTODO', $properties);
    }

    private function resolveAssignee(Task $task, ?User $forAssignee): ?User
    {
        if ($forAssignee instanceof User) {
            return $forAssignee;
        }

        return $task->assignee();
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function descriptions(Task $task): array
    {
        $text = $task->plainNotes();

        if ($text === null) {
            return [null, ''];
        }

        $html = '<!DOCTYPE html><html lang="en"><body>'
            .Str::markdown((string) $task->description, [
                'renderer' => [
                    'block_separator' => "\n",
                    'inner_separator' => "\n",
                    'soft_break' => '<br \\>',
                ],
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ])
            .'</body></html>';

        return [$text, $html];
    }
}
