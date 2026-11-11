<?php

namespace App\Listeners;

use App\Events\TaskAssigneeChanged;
use App\Support\TaskNotificationWindow;

class SendTaskAssigneeEmail
{
    public function handle(TaskAssigneeChanged $event): void
    {
        if ($event->assignee->isSuspended() || ! $event->assignee->hasVerifiedEmail()) {
            return;
        }

        if ($event->cancelled) {
            TaskNotificationWindow::push(
                $event->task,
                TaskNotificationWindow::CANCELLED,
                assigneeUserId: (string) $event->assignee->getKey(),
            );

            return;
        }

        if ($event->updateOnly) {
            TaskNotificationWindow::push(
                $event->task,
                TaskNotificationWindow::UPDATED,
                assigneeUserId: (string) $event->assignee->getKey(),
                changes: $event->changes,
            );

            return;
        }

        TaskNotificationWindow::push(
            $event->task,
            TaskNotificationWindow::INVITE,
            assigneeUserId: (string) $event->assignee->getKey(),
        );
    }
}
