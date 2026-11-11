<?php

namespace App\Support;

use App\Enums\TaskAttendeeStatus;
use App\Models\Task;
use App\Models\TaskAttendance;
use App\Models\User;

class TaskAssigneeResponseLinks
{
    /**
     * @return list<array{label: string, url: string, color: string}>
     */
    public static function choices(Task $task, User $assignee, ?TaskAttendance $attendance): array
    {
        if (! $attendance?->token || $task->isCancelled()) {
            return [];
        }

        if (
            $attendance->user_id !== $assignee->getKey()
            || (! $attendance->needsAction() && $attendance->status !== TaskAttendeeStatus::Tentative)
        ) {
            return [];
        }

        return [
            self::choice(__('Accept'), $task, $assignee, $attendance->token, TaskAttendeeStatus::Accepted, '#48bb78'),
            self::choice(__('Decline'), $task, $assignee, $attendance->token, TaskAttendeeStatus::Declined, '#e53e3e'),
        ];
    }

    /**
     * @return array{label: string, url: string, color: string}
     */
    private static function choice(
        string $label,
        Task $task,
        User $assignee,
        string $token,
        TaskAttendeeStatus $status,
        string $color,
    ): array {
        return [
            'label' => $label,
            'url' => self::url($task, $assignee, $token, $status),
            'color' => $color,
        ];
    }

    public static function url(Task $task, User $assignee, string $token, ?TaskAttendeeStatus $choice = null): string
    {
        return route('respond-task', array_filter([
            $task,
            $assignee,
            'invite' => $token,
            'choice' => $choice?->value,
            'sequence' => $task->sequence,
        ]));
    }
}
