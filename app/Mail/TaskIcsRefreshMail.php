<?php

namespace App\Mail;

use App\Enums\CalendarMethod;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class TaskIcsRefreshMail extends TaskStatusMail implements ShouldQueue
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        Task $task,
        User $assignee,
        public CalendarMethod $method = CalendarMethod::Request,
    ) {
        parent::__construct($task, $assignee);
        $this->afterCommit();
    }

    protected function subjectKind(): string
    {
        return $this->method === CalendarMethod::Cancel
            ? __('Cancelled')
            : __('Updated');
    }

    protected function calendarMethod(): CalendarMethod
    {
        return $this->method;
    }

    protected function calendarNeedsRsvp(): bool
    {
        if ($this->method === CalendarMethod::Cancel) {
            return false;
        }

        return parent::calendarNeedsRsvp();
    }

    protected function statusHeading(): string
    {
        return $this->method === CalendarMethod::Cancel
            ? __('Task cancelled')
            : __('Task details');
    }

    protected function statusIntro(): string
    {
        return $this->method === CalendarMethod::Cancel
            ? __('Here are the current details for this cancelled task.')
            : __('Here are the current details for this task.');
    }
}
