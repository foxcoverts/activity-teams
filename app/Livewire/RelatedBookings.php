<?php

namespace App\Livewire;

use App\Filament\Teams\Resources\Bookings\BookingResource as TeamsBookingResource;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Support\Palette;
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

class RelatedBookings extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?Booking $record = null;

    public function mount(?Booking $record = null): void
    {
        abort_unless($record instanceof Booking, 404);

        $this->record = $this->booking($record->getKey());

        abort_unless($this->viewer()?->can('view', $this->record) ?? false, 403);
    }

    public function add(string $relatedId): void
    {
        abort_unless($this->record instanceof Booking, 404);

        $booking = $this->booking($this->record->getKey());
        abort_unless($this->canManage($booking), 403);

        $related = $this->candidateQuery($booking)->whereKey($relatedId)->first();
        abort_if($related === null, 404);
        abort_unless($this->viewer()?->can('relate', [$booking, $related]) ?? false, 403);

        $booking->relate($related);
        $this->record = $booking->unsetRelation('related');

        Notification::make()->success()->title(__('Added related booking.'))->send();
    }

    public function remove(string $relatedId): void
    {
        abort_unless($this->record instanceof Booking, 404);

        $booking = $this->booking($this->record->getKey());
        abort_unless($this->canManage($booking), 403);

        $related = $booking->related()->whereKey($relatedId)->first();
        abort_if($related === null, 404);
        abort_unless($this->viewer()?->can('relate', [$booking, $related]) ?? false, 403);

        $booking->unrelate($related);
        $this->record = $booking->unsetRelation('related');

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
            ->color(fn (): ?array => $this->record instanceof Booking
                && $this->booking($this->record->getKey())->isPast()
                ? Palette::neutral()
                : null)
            ->modalHeading(__('Add related booking'))
            ->modalSubmitActionLabel(__('Add'))
            ->visible(fn (): bool => $this->record instanceof Booking
                && $this->canManage($this->booking($this->record->getKey()))
                && $this->candidates($this->booking($this->record->getKey()))->isNotEmpty())
            ->schema([
                Select::make('related_id')
                    ->label(__('Booking'))
                    ->options(fn (): array => $this->candidates($this->booking($this->record->getKey()))
                        ->mapWithKeys(fn (Booking $booking): array => [
                            $booking->getKey() => $booking->relatedLabel(),
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $this->add((string) $data['related_id']);
            });
    }

    public function render(): View
    {
        abort_unless($this->record instanceof Booking, 404);

        $booking = $this->booking($this->record->getKey());
        $viewer = $this->viewer();
        abort_unless($viewer instanceof User && $viewer->can('view', $booking), 403);

        $items = $booking->related
            ->filter(fn (Booking $related): bool => $viewer->can('view', $related))
            ->values()
            ->map(fn (Booking $related): array => [
                'id' => $related->getKey(),
                'label' => $related->relatedLabel(),
                'url' => $this->urlFor($related, $booking),
            ]);

        return view('filament.bookings.related-bookings', [
            'items' => $items,
            'canManage' => $this->canManage($booking),
            'showPanel' => static::shouldShowPanel($booking, $viewer),
        ]);
    }

    public static function shouldShowPanel(Booking $booking, User $viewer): bool
    {
        $booking->loadMissing('related');

        $hasVisibleRelated = $booking->related
            ->contains(fn (Booking $related): bool => $viewer->can('view', $related));

        if ($hasVisibleRelated) {
            return true;
        }

        if (! $viewer->can('update', $booking)) {
            return false;
        }

        return static::candidateQueryFor($booking)->exists();
    }

    private function canManage(Booking $booking): bool
    {
        return $this->viewer()?->can('update', $booking) ?? false;
    }

    private function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function booking(string $id): Booking
    {
        return Booking::query()->findOrFail($id);
    }

    /**
     * @return Collection<int, Booking>
     */
    private function candidates(Booking $booking): Collection
    {
        return static::candidateQueryFor($booking)
            ->with('activity')
            ->ordered()
            ->get();
    }

    /**
     * @return Builder<Booking>
     */
    private function candidateQuery(Booking $booking): Builder
    {
        return static::candidateQueryFor($booking);
    }

    /**
     * @return Builder<Booking>
     */
    public static function candidateQueryFor(Booking $booking): Builder
    {
        return Booking::query()
            ->where('team_id', $booking->team_id)
            ->whereKeyNot($booking->getKey())
            ->whereKeyNot($booking->related()->allRelatedIds())
            ->future()
            ->notCancelled();
    }

    private function urlFor(Booking $related, Booking $current): string
    {
        $related->loadMissing('team');
        $team = $related->team ?? $current->team;

        if (! $team instanceof Team) {
            $current->loadMissing('team');
            $team = $current->team;
        }

        return TeamsBookingResource::getUrl('view', ['record' => $related], panel: 'teams', tenant: $team);
    }
}
