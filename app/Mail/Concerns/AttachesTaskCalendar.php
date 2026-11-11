<?php

namespace App\Mail\Concerns;

use App\Enums\CalendarMethod;
use App\Enums\TaskAttendeeStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskIcsFactory;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

trait AttachesTaskCalendar
{
    abstract protected function task(): Task;

    abstract protected function assignee(): User;

    abstract protected function calendarMethod(): CalendarMethod;

    protected function attachCalendarData(Email $message): void
    {
        $task = $this->task();
        $assignee = $this->assignee();
        $method = $this->calendarMethod();

        $ics = app(TaskIcsFactory::class)->make(
            [$task],
            $task->summary,
            method: $method,
            forAssignee: $assignee,
            rsvp: $this->calendarNeedsRsvp(),
        );

        $inline = new DataPart($ics, filename: 'task', contentType: 'text/calendar');
        $inline->asInline();
        $inline->getHeaders()->addParameterizedHeader('Content-Type', 'text/calendar', [
            'method' => $method->value,
            'charset' => 'utf-8',
            'component' => 'vtodo',
        ]);
        $message->addPart($inline);

        $download = new DataPart($ics, filename: 'task.ics', contentType: 'text/calendar');
        $download->getHeaders()->addParameterizedHeader('Content-Type', 'text/calendar', [
            'method' => $method->value,
            'charset' => 'utf-8',
            'component' => 'vtodo',
            'name' => 'task.ics',
        ]);
        $message->addPart($download);
    }

    protected function calendarNeedsRsvp(): bool
    {
        $attendance = $this->task()->attendance;

        if ($attendance?->user_id !== $this->assignee()->getKey()) {
            return false;
        }

        return match ($attendance->status) {
            TaskAttendeeStatus::NeedsAction,
            TaskAttendeeStatus::Tentative,
            TaskAttendeeStatus::Accepted => true,
            default => false,
        };
    }
}
