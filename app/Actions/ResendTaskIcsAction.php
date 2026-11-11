<?php

namespace App\Actions;

use App\Enums\CalendarMethod;
use App\Enums\MailChannel;
use App\Mail\TaskIcsRefreshMail;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskHeldNotification;
use App\Support\MailPreference;
use Illuminate\Support\Facades\Mail;

class ResendTaskIcsAction
{
    public function __invoke(Task $task, User $assignee, bool $force = false): void
    {
        if ($assignee->isSuspended() || ! $assignee->hasVerifiedEmail()) {
            return;
        }

        if (! $task->isAssignedTo($assignee)) {
            return;
        }

        $task->loadMissing(['venue', 'team', 'attendance', 'bookings']);

        $channelActive = $task->assigneeHasCalendarChannel();

        if (! $force && ! $channelActive && ! MailPreference::allows($assignee, MailChannel::TasksMail, $task)) {
            $assignee->notify(new TaskHeldNotification(
                $task,
                __('A calendar copy of :summary is in your inbox.', [
                    'summary' => $task->summary,
                ]),
            ));

            return;
        }

        $method = $task->isCancelled()
            ? CalendarMethod::Cancel
            : CalendarMethod::Request;

        Mail::to($assignee)->send(new TaskIcsRefreshMail($task, $assignee, $method));
    }
}
