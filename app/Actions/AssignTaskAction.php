<?php

namespace App\Actions;

use App\Enums\TaskAttendeeStatus;
use App\Events\TaskAssigneeChanged;
use App\Models\Task;
use App\Models\TaskAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignTaskAction
{
    /**
     * @param  bool  $invite  When true, PARTSTAT is needs-action and an invite is sent. When false, the person is added as accepted with no invite mail.
     */
    public function __invoke(Task $task, User $assignee, User $actor, bool $invite = true): void
    {
        Gate::forUser($actor)->authorize('update', $task);

        $task->loadMissing('team', 'attendance.user');

        if ($task->team === null || ! $assignee->isCurrentMemberOf($task->team)) {
            throw ValidationException::withMessages([
                'assignee_id' => __('Choose a current member of this team.'),
            ]);
        }

        if ($assignee->isSuspended() || ! $assignee->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'assignee_id' => __('Choose an active member with a verified email address.'),
            ]);
        }

        $previous = $task->attendance;
        $status = $invite ? TaskAttendeeStatus::NeedsAction : TaskAttendeeStatus::Accepted;

        if (
            $previous instanceof TaskAttendance
            && $previous->user_id === $assignee->getKey()
            && $previous->status === $status
        ) {
            return;
        }

        DB::transaction(function () use ($task, $assignee, $actor, $previous, $invite, $status): void {
            if ($previous instanceof TaskAttendance) {
                $previousUser = $previous->user;
                $hadChannel = $previous->hasCalendarChannel();
                $samePerson = $previous->user_id === $assignee->getKey();
                $previous->delete();
                $task->unsetRelation('attendance');

                if ($previousUser instanceof User && $hadChannel && ! $samePerson) {
                    event(new TaskAssigneeChanged($task, $previousUser, cancelled: true));
                }
            }

            $attendance = new TaskAttendance([
                'task_id' => $task->getKey(),
                'user_id' => $assignee->getKey(),
                'status' => $status,
            ]);
            $attendance->save();
            $task->setRelation('attendance', $attendance->load('user'));

            $task->bumpSequence();

            activity()
                ->causedBy($actor)
                ->performedOn($task)
                ->withProperties([
                    'user_id' => $assignee->getKey(),
                    'invite' => $invite,
                    'status' => $status->value,
                ])
                ->event('assignee_assigned')
                ->log($invite ? 'Assignee invited' : 'Assignee added');

            if ($invite) {
                event(new TaskAssigneeChanged($task, $assignee, cancelled: false));
            }
        });
    }
}
