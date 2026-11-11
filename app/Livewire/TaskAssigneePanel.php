<?php

namespace App\Livewire;

use App\Actions\AssignTaskAction;
use App\Actions\ClearTaskAssigneeAction;
use App\Actions\ResendTaskIcsAction;
use App\Actions\RespondToTaskAssigneeAction;
use App\Enums\TaskAttendeeStatus;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Filament\Teams\Resources\Tasks\Schemas\TaskForm;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TaskAssigneePanel extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?Task $record = null;

    public function mount(?Task $record = null): void
    {
        abort_unless($record instanceof Task, 404);

        $this->record = $this->task($record->getKey());

        abort_unless($this->viewer()?->can('view', $this->record) ?? false, 403);
    }

    public function assigneeGroup(): ActionGroup
    {
        return ActionGroup::make([
            $this->assignAction(),
            $this->clearAction(),
            $this->overrideStatusAction(),
            $this->downloadIcsAction(),
            $this->sendToEmailAction(),
        ])
            ->label(__('Assignee'))
            ->hiddenLabel()
            ->tooltip(__('Assignee'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->button()
            ->visible(fn (): bool => $this->hasAnyAssigneeAction())
            ->livewire($this);
    }

    public function assignAction(): Action
    {
        return Action::make('assign')
            ->label(fn (): string => $this->task($this->record->getKey())->attendance !== null
                ? __('Reassign')
                : __('Assign'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => $this->canManage())
            ->fillForm(fn (): array => [
                'assignee_id' => $this->task($this->record->getKey())->attendance?->user_id,
                'invite' => true,
            ])
            ->schema([
                Select::make('assignee_id')
                    ->label(__('Assignee'))
                    ->options(fn (): array => TaskForm::assigneeOptions())
                    ->searchable()
                    ->required()
                    ->native(false)
                    ->live(),
                TaskForm::inviteToggle(),
            ])
            ->action(function (array $data): void {
                $actor = $this->viewer();
                $assignee = User::query()->find($data['assignee_id'] ?? null);
                $invite = (bool) ($data['invite'] ?? true);

                if (! $actor instanceof User || ! $assignee instanceof User) {
                    return;
                }

                app(AssignTaskAction::class)(
                    $this->task($this->record->getKey()),
                    $assignee,
                    $actor,
                    invite: $invite,
                );

                $this->refreshRecord();

                Notification::make()
                    ->success()
                    ->title($invite ? __('Invited') : __('Assigned'))
                    ->send();
            });
    }

    public function clearAction(): Action
    {
        return Action::make('clear')
            ->label(__('Clear assignee'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color(Palette::destructive())
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->canManage()
                && $this->task($this->record->getKey())->attendance !== null)
            ->action(function (): void {
                $actor = $this->viewer();

                if (! $actor instanceof User) {
                    return;
                }

                app(ClearTaskAssigneeAction::class)($this->task($this->record->getKey()), $actor);
                $this->refreshRecord();

                Notification::make()->success()->title(__('Assignee cleared'))->send();
            });
    }

    public function overrideStatusAction(): Action
    {
        return Action::make('overrideStatus')
            ->label(__('Update response'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (): bool => $this->task($this->record->getKey())->attendance !== null
                && ($this->canManage() || $this->canRespondAsAssignee()))
            ->fillForm(fn (): array => [
                'status' => $this->task($this->record->getKey())->attendance?->status?->value,
            ])
            ->schema([
                Select::make('status')
                    ->label(__('Response'))
                    ->options(TaskAttendeeStatus::class)
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data): void {
                $actor = $this->viewer();
                $task = $this->task($this->record->getKey());
                $assignee = $task->assignee();
                $status = $data['status'] ?? null;

                if (is_string($status)) {
                    $status = TaskAttendeeStatus::tryFrom($status);
                }

                if (! $actor instanceof User || ! $assignee instanceof User || ! $status instanceof TaskAttendeeStatus) {
                    return;
                }

                app(RespondToTaskAssigneeAction::class)(
                    $task,
                    $assignee,
                    $status,
                    actor: $actor,
                );

                $this->refreshRecord();

                Notification::make()->success()->title(__('Response updated'))->send();
            });
    }

    public function downloadIcsAction(): Action
    {
        return Action::make('downloadIcs')
            ->label(__('Add to calendar'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->url(fn (): string => route('task.show.ics', $this->task($this->record->getKey())))
            ->openUrlInNewTab()
            ->visible(fn (): bool => $this->canUseCalendarActions());
    }

    public function sendToEmailAction(): Action
    {
        return Action::make('sendToEmail')
            ->label(__('Send to email'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->visible(fn (): bool => $this->canUseCalendarActions())
            ->action(function (): void {
                $actor = $this->viewer();
                $task = $this->task($this->record->getKey());

                if (! $actor instanceof User || ! $task->isAssignedTo($actor)) {
                    return;
                }

                app(ResendTaskIcsAction::class)($task, $actor, force: true);

                Notification::make()->success()->title(__('Calendar invite sent'))->send();
            });
    }

    public function render(): View
    {
        abort_unless($this->record instanceof Task, 404);

        $task = $this->task($this->record->getKey());
        $viewer = $this->viewer();
        abort_unless($viewer instanceof User && $viewer->can('view', $task), 403);

        if (! static::shouldShowPanel($task, $viewer)) {
            return view('filament.tasks.assignee-panel', [
                'task' => $task,
                'assignee' => null,
                'attendance' => null,
                'memberUrl' => null,
                'showTools' => false,
                'showPanel' => false,
            ]);
        }

        $assignee = $task->assignee();

        return view('filament.tasks.assignee-panel', [
            'task' => $task,
            'assignee' => $assignee,
            'attendance' => $task->attendance,
            'memberUrl' => $assignee instanceof User ? $this->memberUrl($task, $assignee, $viewer) : null,
            'showTools' => $this->hasAnyAssigneeAction(),
            'showPanel' => true,
        ]);
    }

    public static function shouldShowPanel(Task $task, User $viewer): bool
    {
        if (! $viewer->can('view', $task)) {
            return false;
        }

        $task->loadMissing('attendance');

        if ($task->attendance !== null) {
            return true;
        }

        return $viewer->can('update', $task);
    }

    private function hasAnyAssigneeAction(): bool
    {
        $task = $this->task($this->record->getKey());

        return $this->canManage()
            || ($task->attendance !== null && $this->canRespondAsAssignee())
            || $this->canUseCalendarActions();
    }

    private function canManage(): bool
    {
        $viewer = $this->viewer();

        return $viewer instanceof User
            && $viewer->can('update', $this->task($this->record->getKey()));
    }

    private function canRespondAsAssignee(): bool
    {
        $viewer = $this->viewer();

        return $viewer instanceof User
            && $viewer->can('respondAsAssignee', $this->task($this->record->getKey()));
    }

    private function canUseCalendarActions(): bool
    {
        $viewer = $this->viewer();
        $task = $this->task($this->record->getKey());

        if (! $viewer instanceof User || ! $task->isAssignedTo($viewer)) {
            return false;
        }

        return in_array($task->attendance?->status, [
            TaskAttendeeStatus::NeedsAction,
            TaskAttendeeStatus::Accepted,
            TaskAttendeeStatus::Tentative,
            TaskAttendeeStatus::InProcess,
        ], true);
    }

    private function memberUrl(Task $task, User $assignee, User $viewer): ?string
    {
        $task->loadMissing('team');
        $team = $task->team;

        if (! $team instanceof Team || ! $assignee->isCurrentMemberOf($team)) {
            return null;
        }

        $mayLink = $viewer->manages($team)
            || ($viewer->isSystemAdmin() && MemberResource::canView($assignee));

        if (! $mayLink || ! MemberResource::canView($assignee)) {
            return null;
        }

        return MemberResource::getUrl('view', ['record' => $assignee], panel: 'teams', tenant: $team);
    }

    private function refreshRecord(): void
    {
        $this->record = $this->task($this->record->getKey());
    }

    private function task(string $id): Task
    {
        return Task::query()->with(['attendance.user', 'team'])->findOrFail($id);
    }

    private function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
