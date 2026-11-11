<?php

namespace App\Mail;

use App\Enums\CalendarMethod;

class TaskCancelledMail extends TaskStatusMail
{
    protected function subjectKind(): string
    {
        return __('Cancelled');
    }

    protected function statusHeading(): string
    {
        return __('Task cancelled');
    }

    protected function statusIntro(): string
    {
        return $this->task->isCancelled()
            ? __('This task has been cancelled.')
            : __('You are no longer assigned to this task.');
    }

    protected function calendarMethod(): CalendarMethod
    {
        return CalendarMethod::Cancel;
    }

    protected function calendarNeedsRsvp(): bool
    {
        return false;
    }
}
