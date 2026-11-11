<?php

namespace App\Actions;

use App\Events\TaskAssigneeChanged;
use App\Models\Task;
use App\Models\TaskAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClearTaskAssigneeAction
{
    public function __invoke(Task $task, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $task);

        $task->loadMissing('attendance.user');
        $attendance = $task->attendance;

        if (! $attendance instanceof TaskAttendance) {
            return;
        }

        DB::transaction(function () use ($task, $actor, $attendance): void {
            $previousUser = $attendance->user;
            $hadChannel = $attendance->hasCalendarChannel();
            $attendance->delete();
            $task->unsetRelation('attendance');
            $task->bumpSequence();

            activity()
                ->causedBy($actor)
                ->performedOn($task)
                ->withProperties(['user_id' => $previousUser?->getKey()])
                ->event('assignee_cleared')
                ->log('Assignee cleared');

            if ($previousUser instanceof User && $hadChannel) {
                event(new TaskAssigneeChanged($task, $previousUser, cancelled: true));
            }
        });
    }
}
