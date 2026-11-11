<?php

namespace App\Livewire;

use App\Actions\RespondToTaskAssigneeAction;
use App\Enums\TaskAttendeeStatus;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\TaskAttendance;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class RespondToTask extends Component
{
    public Task $task;

    public User $assignee;

    public string $invite = '';

    public TaskAttendeeStatus $response;

    public bool $changing = false;

    public ?string $pendingChoice = null;

    public bool $sequenceStale = false;

    public function mount(Task $task, User $assignee): void
    {
        $this->task = $task->load(['venue', 'team', 'attendance']);
        $this->assignee = $assignee;
        $this->invite = (string) request('invite');

        $attendance = $this->task->attendance;
        abort_unless(
            $attendance instanceof TaskAttendance
            && $attendance->user_id === $assignee->getKey()
            && $this->invite === $attendance->token,
            403,
            __('Invitation invalid'),
        );

        $this->response = $attendance->status;
        $this->sequenceStale = $this->linkSequenceIsStale();
        $this->pendingChoice = $this->requestedChoice();
    }

    public function cancelChoice(): void
    {
        $this->pendingChoice = null;
    }

    public function changeResponse(): void
    {
        if ($this->task->isCancelled()) {
            return;
        }

        $this->changing = true;
    }

    public function keepResponse(): void
    {
        $this->changing = false;
    }

    public function respond(string $status): void
    {
        $choice = TaskAttendeeStatus::tryFrom($status);

        if (! in_array($choice, [TaskAttendeeStatus::Accepted, TaskAttendeeStatus::Declined], true)) {
            $this->addError('status', __('Choose Accept or Decline.'));

            return;
        }

        try {
            app(RespondToTaskAssigneeAction::class)(
                $this->task,
                $this->assignee,
                $choice,
                via: 'token',
            );
        } catch (ValidationException $e) {
            $this->addError('status', collect($e->errors())->flatten()->first() ?? $e->getMessage());

            return;
        }

        $this->task->unsetRelation('attendance');
        $this->task->load('attendance');
        $this->response = $this->task->attendance->status;
        $this->changing = false;
        $this->pendingChoice = null;
        $this->sequenceStale = false;
    }

    public function pendingChoiceLabel(): ?string
    {
        return match (TaskAttendeeStatus::tryFrom((string) $this->pendingChoice)) {
            TaskAttendeeStatus::Accepted => __('Accept'),
            TaskAttendeeStatus::Declined => __('Decline'),
            default => null,
        };
    }

    public function acceptsResponses(): bool
    {
        return ! $this->task->isCancelled();
    }

    public function taskUrl(): string
    {
        $this->task->loadMissing('team');

        return TaskResource::getUrl('view', ['record' => $this->task], panel: 'teams', tenant: $this->task->team);
    }

    private function requestedChoice(): ?string
    {
        $choice = request('choice');

        if (! is_string($choice) || $this->task->isCancelled()) {
            return null;
        }

        $status = TaskAttendeeStatus::tryFrom($choice);

        if (! in_array($status, [TaskAttendeeStatus::Accepted, TaskAttendeeStatus::Declined], true) || $status === $this->response) {
            return null;
        }

        return $status->value;
    }

    private function linkSequenceIsStale(): bool
    {
        $sequence = request('sequence');

        if ($sequence === null || $sequence === '') {
            return false;
        }

        if (! is_numeric($sequence)) {
            return true;
        }

        return (int) $sequence < (int) $this->task->sequence;
    }

    public function render(): View
    {
        return view('livewire.respond-to-task')
            ->title(__('Respond to task'));
    }
}
