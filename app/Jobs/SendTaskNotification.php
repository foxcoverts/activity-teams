<?php

namespace App\Jobs;

use App\Enums\MailChannel;
use App\Enums\TaskAttendeeStatus;
use App\Mail\TaskCancelledMail;
use App\Mail\TaskChangedMail;
use App\Mail\TaskInviteMail;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskHeldNotification;
use App\Support\MailPreference;
use App\Support\TaskNotificationWindow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendTaskNotification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Task $task,
        public string $token,
    ) {}

    public function handle(): void
    {
        $pending = TaskNotificationWindow::claim($this->task, $this->token);

        if ($pending === null) {
            return;
        }

        $task = $this->task->fresh(['venue', 'attendance.user', 'team']);

        if (! $task) {
            return;
        }

        $assignee = $this->resolveAssignee($task, $pending['assignee_user_id'] ?? null);

        if (! $assignee instanceof User) {
            return;
        }

        if ($assignee->isSuspended() || ! $assignee->hasVerifiedEmail()) {
            return;
        }

        $kinds = $pending['kinds'] ?? [];

        if (in_array(TaskNotificationWindow::CANCELLED, $kinds, true) || $task->isCancelled()) {
            $this->send(
                $task,
                $assignee,
                fn (): TaskCancelledMail => new TaskCancelledMail($task, $assignee),
                MailChannel::TasksUpdates,
                skipDeclined: true,
                hadCalendarChannel: true,
            );

            if ($task->isCancelled()) {
                $task->clearCalendarChannel($assignee);
            }

            return;
        }

        if (in_array(TaskNotificationWindow::UPDATED, $kinds, true)) {
            if (! $task->isAssignedTo($assignee)) {
                return;
            }

            $changes = array_values(array_filter(
                $pending['changes'] ?? [],
                fn (mixed $change): bool => is_string($change),
            ));

            $this->send(
                $task,
                $assignee,
                fn (): TaskChangedMail => new TaskChangedMail($task, $assignee, $changes),
                MailChannel::TasksUpdates,
            );

            return;
        }

        if (! $task->isAssignedTo($assignee) || $task->isCancelled()) {
            return;
        }

        $this->send(
            $task,
            $assignee,
            fn (): TaskInviteMail => new TaskInviteMail($task, $assignee),
            MailChannel::TasksMail,
        );
    }

    /**
     * @param  callable(): TaskInviteMail|TaskChangedMail|TaskCancelledMail  $message
     */
    private function send(
        Task $task,
        User $assignee,
        callable $message,
        MailChannel $channel,
        bool $skipDeclined = false,
        bool $hadCalendarChannel = false,
    ): void {
        $attendance = $task->attendance;

        if (
            $skipDeclined
            && $attendance?->user_id === $assignee->getKey()
            && $attendance->status === TaskAttendeeStatus::Declined
            && ! $this->declinedViaImip($task, $assignee)
        ) {
            return;
        }

        $channelActive = $hadCalendarChannel
            || ($task->isAssignedTo($assignee) && $task->assigneeHasCalendarChannel());

        if (! $channelActive && ! MailPreference::allows($assignee, $channel, $task)) {
            $assignee->notify(new TaskHeldNotification(
                $task,
                $channel === MailChannel::TasksMail
                    ? __('You were assigned a task: :summary.', ['summary' => $task->summary])
                    : __('An update to :summary is in your inbox.', ['summary' => $task->summary]),
            ));

            return;
        }

        Mail::to($assignee)->send($message());

        if ($task->isCancelled()) {
            $task->clearCalendarChannel($assignee);
        }
    }

    private function resolveAssignee(Task $task, ?string $userId): ?User
    {
        if (filled($userId)) {
            return User::query()->find($userId);
        }

        return $task->assignee();
    }

    private function declinedViaImip(Task $task, User $assignee): bool
    {
        $activity = $task->activitiesAsSubject()
            ->where('event', 'assignee_responded')
            ->where('properties->user_id', $assignee->getKey())
            ->where('properties->status', TaskAttendeeStatus::Declined->value)
            ->latest()
            ->first();

        return $activity?->getProperty('via') === 'imip';
    }
}
