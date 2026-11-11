<?php

namespace App\Livewire;

use App\Enums\BookingStatus;
use App\Filament\Teams\Resources\Bookings\BookingResource as TeamsBookingResource;
use App\Models\Booking;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class TaskRelatedBookings extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?Task $record = null;

    public bool $showPast = false;

    public function mount(?Task $record = null): void
    {
        abort_unless($record instanceof Task, 404);

        $this->record = $this->task($record->getKey());

        abort_unless($this->viewer()?->can('view', $this->record) ?? false, 403);
    }

    public function toggleShowPast(): void
    {
        $this->showPast = ! $this->showPast;
    }

    public function add(string $bookingId): void
    {
        $task = $this->task($this->record->getKey());
        abort_unless($this->canManage($task), 403);

        $booking = $this->candidateQuery($task)->whereKey($bookingId)->first();
        abort_if($booking === null, 404);

        $task->attachBooking($booking);
        $this->record = $task->unsetRelation('bookings');

        Notification::make()->success()->title(__('Added related booking.'))->send();
    }

    public function remove(string $bookingId): void
    {
        $task = $this->task($this->record->getKey());
        abort_unless($this->canManage($task), 403);

        $booking = $task->bookings()->whereKey($bookingId)->first();
        abort_if($booking === null, 404);

        $task->detachBooking($booking);
        $this->record = $task->unsetRelation('bookings');

        Notification::make()->success()->title(__('Related booking removed.'))->send();
    }

    public function addAction(): Action
    {
        return Action::make('add')
            ->label(__('Add'))
            ->hiddenLabel()
            ->tooltip(__('Add'))
            ->icon(Heroicon::OutlinedPlus)
            ->button()
            ->modalHeading(__('Add related booking'))
            ->modalSubmitActionLabel(__('Add'))
            ->visible(fn (): bool => $this->record instanceof Task
                && $this->canManage($this->task($this->record->getKey()))
                && $this->candidates($this->task($this->record->getKey()))->isNotEmpty())
            ->schema([
                Select::make('booking_id')
                    ->label(__('Booking'))
                    ->options(fn (): array => $this->candidates($this->task($this->record->getKey()))
                        ->mapWithKeys(fn (Booking $booking): array => [
                            $booking->getKey() => $booking->relatedLabel(),
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->add((string) $data['booking_id']);
            });
    }

    public function render(): View
    {
        abort_unless($this->record instanceof Task, 404);

        $task = $this->task($this->record->getKey());
        $viewer = $this->viewer();
        abort_unless($viewer instanceof User && $viewer->can('view', $task), 403);

        $attached = $task->bookings()
            ->with(['activity', 'audience', 'team'])
            ->get()
            ->filter(fn (Booking $booking): bool => $viewer->can('view', $booking))
            ->sortBy(fn (Booking $booking): array => [
                $booking->start_at?->isPast() ? 1 : 0,
                $booking->start_at?->timestamp ?? PHP_INT_MAX,
            ])
            ->values();

        $visible = $this->showPast
            ? $attached
            : $attached->filter(fn (Booking $booking): bool => $this->isFutureNotCancelled($booking))->values();

        $hiddenPastCount = $attached
            ->reject(fn (Booking $booking): bool => $this->isFutureNotCancelled($booking))
            ->count();

        $items = $visible->map(fn (Booking $booking): array => [
            'id' => $booking->getKey(),
            'label' => $booking->relatedLabel(),
            'url' => $this->urlFor($booking, $task),
            'past' => ! $this->isFutureNotCancelled($booking),
        ]);

        return view('filament.tasks.related-bookings', [
            'items' => $items,
            'canManage' => $this->canManage($task),
            'showPanel' => static::shouldShowPanel($task, $viewer),
            'showPast' => $this->showPast,
            'hiddenPastCount' => $hiddenPastCount,
        ]);
    }

    public static function shouldShowPanel(Task $task, User $viewer): bool
    {
        if (! $viewer->can('view', $task)) {
            return false;
        }

        $task->loadMissing('bookings');

        $hasVisible = $task->bookings
            ->contains(fn (Booking $booking): bool => $viewer->can('view', $booking));

        if ($hasVisible) {
            return true;
        }

        return $viewer->can('attachBooking', $task)
            && static::candidateQueryFor($task, includePast: true)->exists();
    }

    private function canManage(Task $task): bool
    {
        return $this->viewer()?->can('attachBooking', $task) ?? false;
    }

    private function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function task(string $id): Task
    {
        return Task::query()->with('bookings')->findOrFail($id);
    }

    /**
     * @return Collection<int, Booking>
     */
    private function candidates(Task $task): Collection
    {
        return $this->candidateQuery($task)
            ->with(['activity', 'audience'])
            ->orderBy('start_at')
            ->get();
    }

    /**
     * @return Builder<Booking>
     */
    private function candidateQuery(Task $task): Builder
    {
        return static::candidateQueryFor($task, includePast: $this->showPast);
    }

    /**
     * @return Builder<Booking>
     */
    public static function candidateQueryFor(Task $task, bool $includePast = false): Builder
    {
        $query = Booking::query()
            ->where('team_id', $task->team_id)
            ->whereKeyNot($task->bookings()->allRelatedIds());

        if (! $includePast) {
            $query->future()->notCancelled();
        }

        return $query;
    }

    private function isFutureNotCancelled(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Cancelled) {
            return false;
        }

        return $booking->end_at !== null && $booking->end_at->greaterThanOrEqualTo(now()->startOfDay());
    }

    private function urlFor(Booking $booking, Task $task): string
    {
        $booking->loadMissing('team');
        $team = $booking->team ?? $task->team;

        if (! $team instanceof Team) {
            $task->loadMissing('team');
            $team = $task->team;
        }

        return TeamsBookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $team);
    }
}
