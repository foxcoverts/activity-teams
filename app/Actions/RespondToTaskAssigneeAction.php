<?php

namespace App\Actions;

use App\Enums\TaskAttendeeStatus;
use App\Enums\TaskStatus;
use App\Events\TaskAssigneeChanged;
use App\Models\Task;
use App\Models\TaskAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RespondToTaskAssigneeAction
{
    public function __invoke(
        Task $task,
        User $assignee,
        TaskAttendeeStatus $status,
        ?string $comment = null,
        ?string $via = null,
        ?User $actor = null,
    ): void {
        $actor ??= $assignee;
        $task->loadMissing('attendance', 'team');

        $attendance = $task->attendance;

        if (! $attendance instanceof TaskAttendance || $attendance->user_id !== $assignee->getKey()) {
            throw ValidationException::withMessages([
                'assignee_id' => __('This person is not assigned to the task.'),
            ]);
        }

        if ($actor->is($assignee)) {
            Gate::forUser($actor)->authorize('respondAsAssignee', $task);
        } else {
            Gate::forUser($actor)->authorize('update', $task);
        }

        DB::transaction(function () use ($task, $attendance, $status, $comment, $via, $actor): void {
            $attendance->status = $status;
            $attendance->comment = filled($comment) ? trim($comment) : null;
            $attendance->save();

            // Task STATUS changes bump SEQUENCE via HasSequence; PARTSTAT-only replies do not.
            $synced = $this->syncTaskStatusFromPartstat($task, $status);

            activity()
                ->causedBy($actor)
                ->performedOn($task)
                ->withProperties(array_filter([
                    'user_id' => $attendance->user_id,
                    'status' => $status->value,
                    'via' => $via,
                ]))
                ->event('assignee_responded')
                ->log('Assignee response recorded');

            // PARTSTAT-only Accept/Decline should not send a "task changed" mail.
            if ($synced) {
                event(new TaskAssigneeChanged(
                    $task,
                    $attendance->user,
                    cancelled: false,
                    updateOnly: true,
                    changes: ['status'],
                ));
            }
        });
    }

    private function syncTaskStatusFromPartstat(Task $task, TaskAttendeeStatus $status): bool
    {
        return match ($status) {
            TaskAttendeeStatus::InProcess => tap(true, function () use ($task): void {
                $task->forceFill([
                    'status' => TaskStatus::InProcess,
                    'started_at' => $task->started_at ?? now(),
                    'completed_at' => null,
                ])->save();
            }),
            TaskAttendeeStatus::Completed => tap(true, function () use ($task): void {
                $task->forceFill([
                    'status' => TaskStatus::Completed,
                    'completed_at' => $task->completed_at ?? now(),
                    'started_at' => $task->started_at ?? now(),
                ])->save();
            }),
            TaskAttendeeStatus::Accepted,
            TaskAttendeeStatus::Tentative,
            TaskAttendeeStatus::NeedsAction,
            TaskAttendeeStatus::Declined => false,
        };
    }
}
