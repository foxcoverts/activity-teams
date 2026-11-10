<?php

namespace App\Livewire;

use App\Actions\SaveTaskDetailsAction;
use App\Enums\TaskPriority;
use App\Enums\TeamFeature;
use App\Filament\Tasks\Concerns\TogglesTaskCompletion;
use App\Filament\Teams\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Teams\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Filament\Venues\VenueLocationSchema;
use App\Models\Booking;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class BookingTasks extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use TogglesTaskCompletion {
        toggleTaskComplete as baseToggleTaskComplete;
    }

    public ?Booking $record = null;

    public function mount(?Booking $record = null): void
    {
        abort_unless($record instanceof Booking, 404);

        $this->record = $this->booking($record->getKey());

        abort_unless($this->viewer()?->can('view', $this->record) ?? false, 403);
    }

    public function toggleTaskComplete(string $taskId): void
    {
        $this->baseToggleTaskComplete($taskId);
        $this->record = $this->booking($this->record->getKey());
    }

    public function remove(string $taskId): void
    {
        $booking = $this->booking($this->record->getKey());
        abort_unless($this->canManage($booking), 403);

        $task = $booking->tasks()->whereKey($taskId)->first();
        abort_if($task === null, 404);

        $booking->detachTask($task);
        $this->record = $booking->unsetRelation('tasks');

        Notification::make()->success()->title(__('Task removed from booking.'))->send();
    }

    public function addExisting(string $taskId): void
    {
        $booking = $this->booking($this->record->getKey());
        abort_unless($this->canManage($booking), 403);

        $task = $this->candidateQuery($booking)->whereKey($taskId)->first();
        abort_if($task === null, 404);

        $booking->attachTask($task);
        $this->record = $booking->unsetRelation('tasks');

        Notification::make()->success()->title(__('Task added.'))->send();
    }

    public function addExistingAction(): Action
    {
        return Action::make('addExisting')
            ->label(__('Add existing'))
            ->button()
            ->color('gray')
            ->modalHeading(__('Add existing task'))
            ->modalSubmitActionLabel(__('Add'))
            ->visible(fn (): bool => $this->record instanceof Booking
                && $this->canManage($this->booking($this->record->getKey()))
                && $this->candidates($this->booking($this->record->getKey()))->isNotEmpty())
            ->schema([
                Select::make('task_id')
                    ->label(__('Task'))
                    ->options(fn (): array => $this->candidates($this->booking($this->record->getKey()))
                        ->mapWithKeys(fn (Task $task): array => [
                            $task->getKey() => $task->summary,
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->addExisting((string) $data['task_id']);
            });
    }

    public function addNewAction(): Action
    {
        return Action::make('addNew')
            ->label(__('Add new'))
            ->button()
            ->modalHeading(__('Add new task'))
            ->modalSubmitActionLabel(__('Create'))
            ->visible(fn (): bool => $this->record instanceof Booking
                && $this->canManage($this->booking($this->record->getKey())))
            ->fillForm(function (): array {
                $booking = $this->booking($this->record->getKey());

                $data = [
                    'priority' => TaskPriority::Medium->value,
                    'venue_id' => $booking->venue_id,
                ];

                $privateVenue = Venue::onlyPrivate()
                    ->where('booking_id', $booking->getKey())
                    ->whereKey($booking->venue_id)
                    ->first();

                if ($privateVenue instanceof Venue) {
                    $data['venue_id'] = BookingForm::OTHER_VENUE;

                    foreach (VenueLocationSchema::venueAttributes() as $attribute) {
                        $data[$attribute] = $privateVenue->{$attribute};
                    }

                    $data['has_map_pin'] = $privateVenue->coords !== null;
                }

                return $data;
            })
            ->schema(fn (): array => TaskForm::components())
            ->action(function (array $data): void {
                $booking = $this->booking($this->record->getKey());
                abort_unless($this->canManage($booking), 403);
                $booking->loadMissing('team');
                abort_unless($booking->team instanceof Team, 404);

                $task = app(SaveTaskDetailsAction::class)->create(
                    TaskForm::mergeVenueData($data),
                    $booking->team,
                );

                $booking->attachTask($task);
                $this->record = $booking->unsetRelation('tasks');

                Notification::make()->success()->title(__('Task created.'))->send();
            });
    }

    public function render(): View
    {
        abort_unless($this->record instanceof Booking, 404);

        $booking = $this->booking($this->record->getKey());
        $viewer = $this->viewer();
        abort_unless($viewer instanceof User && $viewer->can('view', $booking), 403);

        $tasks = $booking->tasks()
            ->orderedForList()
            ->get()
            ->filter(fn (Task $task): bool => $viewer->can('view', $task))
            ->values();

        $items = $tasks->map(function (Task $task) use ($viewer, $booking): array {
            $team = $booking->team ?? $task->team;

            return [
                'id' => $task->getKey(),
                'summary' => $task->summary,
                'url' => $team instanceof Team
                    ? TaskResource::getUrl('view', ['record' => $task], panel: 'teams', tenant: $team)
                    : null,
                'overdue' => $task->isOverdue(),
                'priority' => $task->priority !== TaskPriority::Medium ? $task->priority->getLabel() : null,
                'complete' => $task->isComplete(),
                'cancelled' => $task->isCancelled(),
                'canToggle' => $viewer->can('complete', $task),
                'task' => $task,
            ];
        });

        return view('filament.bookings.booking-tasks', [
            'items' => $items,
            'count' => $items->count(),
            'canManage' => $this->canManage($booking),
            'showPanel' => static::shouldShowPanel($booking, $viewer),
        ]);
    }

    public static function shouldShowPanel(Booking $booking, User $viewer): bool
    {
        $booking->loadMissing('team');

        if (! $booking->team instanceof Team || ! $booking->team->featureEnabled(TeamFeature::Tasks)) {
            return false;
        }

        $booking->loadMissing('tasks.team');

        $hasVisible = $booking->tasks
            ->contains(fn (Task $task): bool => $viewer->can('view', $task));

        if ($hasVisible) {
            return true;
        }

        return $viewer->can('update', $booking)
            && $booking->team->featureEnabled(TeamFeature::Tasks);
    }

    private function canManage(Booking $booking): bool
    {
        $booking->loadMissing('team');

        return ($this->viewer()?->can('update', $booking) ?? false)
            && $booking->team instanceof Team
            && $booking->team->featureEnabled(TeamFeature::Tasks);
    }

    private function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function booking(string $id): Booking
    {
        return Booking::query()->with(['team', 'tasks.team'])->findOrFail($id);
    }

    /**
     * @return Collection<int, Task>
     */
    private function candidates(Booking $booking): Collection
    {
        return $this->candidateQuery($booking)
            ->orderedForList()
            ->get();
    }

    /**
     * @return Builder<Task>
     */
    private function candidateQuery(Booking $booking): Builder
    {
        return Task::query()
            ->where('team_id', $booking->team_id)
            ->whereKeyNot($booking->tasks()->allRelatedIds())
            ->open();
    }
}
