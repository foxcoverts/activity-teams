<?php

namespace App\Filament\Member\Resources\Bookings\RelationManagers;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingGuestList;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class AttendeesRelationManager extends RelationManager
{
    protected static ?string $title = 'Guest list';

    protected static string $relationship = 'attendees';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $actor = auth()->user();

        if (! $ownerRecord instanceof Booking || ! $actor instanceof User) {
            return false;
        }

        $ownerRecord->loadMissing('team');

        return $ownerRecord->team !== null
            && $actor->isCurrentMemberOf($ownerRecord->team);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
            ])
            ->content(fn (): View => $this->guestListContent())
            ->paginated(false)
            ->headerActions([])
            ->recordActions([
                Action::make('qualifications')
                    ->modalHeading(fn (User $record): string => $record->name)
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->authorize(fn (User $record): bool => $this->hasAccessDetail($record, 'qualified'))
                    ->modalContent(fn (User $record): View => view('filament.bookings.person-access-host', [
                        'booking' => $this->getOwnerRecord(),
                        'person' => $record,
                        'panel' => 'qualifications',
                    ])),
                Action::make('heldKeys')
                    ->modalHeading(fn (User $record): string => $record->name)
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->authorize(fn (User $record): bool => $this->hasAccessDetail($record, 'key_holder'))
                    ->modalContent(fn (User $record): View => view('filament.bookings.person-access-host', [
                        'booking' => $this->getOwnerRecord(),
                        'person' => $record,
                        'panel' => 'keys',
                    ])),
            ])
            ->toolbarActions([]);
    }

    private function guestListContent(): View
    {
        $booking = $this->getOwnerRecord();
        $actor = auth()->user();

        if (! $booking instanceof Booking || ! $actor instanceof User) {
            abort(403);
        }

        $booking->loadMissing(['team', 'attendees']);

        $isLead = $booking->isLead($actor);
        $isManager = $booking->team !== null && $actor->manages($booking->team);
        $visibleStatuses = $isLead || $isManager
            ? BookingAttendeeStatus::cases()
            : [BookingAttendeeStatus::Accepted, BookingAttendeeStatus::Tentative];
        $attendees = $booking->attendees;

        if (! $isLead && ! $isManager) {
            $attendees = $attendees->filter(
                fn (User $attendee): bool => in_array(
                    $attendee->attendance?->status,
                    [BookingAttendeeStatus::Accepted, BookingAttendeeStatus::Tentative],
                    true,
                ) || $attendee->is($actor),
            );
            $visibleStatuses = BookingAttendeeStatus::cases();
        }

        $roster = BookingGuestList::forBooking(
            $booking,
            $attendees,
            $visibleStatuses,
            includeSuspended: $isManager,
            includeAccessDetails: $isLead,
        );

        return view('filament.bookings.guest-list', [
            ...$roster,
            'showAccessDetails' => $isLead,
            'showHeading' => false,
            'showQualified' => $isLead && $roster['show_qualified'],
            'showKeys' => $isLead && $roster['show_keys'],
            'panel' => 'member-relation-manager',
        ]);
    }

    private function hasAccessDetail(User $person, string $detail): bool
    {
        $booking = $this->getOwnerRecord();
        $actor = auth()->user();

        if (! $booking instanceof Booking || ! $actor instanceof User || ! $booking->isLead($actor)) {
            return false;
        }

        $booking->loadMissing(['attendees', 'team', 'activity.qualificationRequirements']);
        $roster = BookingGuestList::forBooking(
            $booking,
            $booking->attendees,
            BookingAttendeeStatus::cases(),
            includeSuspended: false,
            includeAccessDetails: true,
        );
        $attendee = collect($roster['groups'])
            ->flatMap(fn (array $group): array => $group['people'])
            ->firstWhere('id', (string) $person->getKey());

        if (! is_array($attendee)) {
            return false;
        }

        return match ($detail) {
            'qualified' => $attendee['qualified'],
            'key_holder' => $attendee['key_badge'] === 'holder',
            default => false,
        };
    }
}
