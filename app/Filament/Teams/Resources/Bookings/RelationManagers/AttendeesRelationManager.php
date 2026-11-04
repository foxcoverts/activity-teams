<?php

namespace App\Filament\Teams\Resources\Bookings\RelationManagers;

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
use Livewire\Attributes\On;

class AttendeesRelationManager extends RelationManager
{
    protected static ?string $title = 'Guest list';

    protected static string $relationship = 'attendees';

    #[On('booking-attendance-updated')]
    public function refreshAfterAttendanceChange(): void {}

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
                Action::make('contactPhone')
                    ->modalHeading(fn (User $record): string => $record->name)
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->authorize(fn (User $record): bool => $this->canViewContact($this->attendee($record), 'viewPhone'))
                    ->modalContent(fn (User $record): View => view('filament.bookings.contact-details-host', [
                        'booking' => $this->getOwnerRecord(),
                        'person' => $record,
                        'kind' => 'phone',
                    ])),
                Action::make('emergencyContact')
                    ->modalHeading(fn (User $record): string => __(':name — emergency contact', ['name' => $record->name]))
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->authorize(fn (User $record): bool => $this->canViewContact($this->attendee($record), 'viewEmergencyContact'))
                    ->modalContent(fn (User $record): View => view('filament.bookings.contact-details-host', [
                        'booking' => $this->getOwnerRecord(),
                        'person' => $record,
                        'kind' => 'emergency',
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

        $roster['groups'] = $this->withContactAccess($roster['groups'], $booking, $actor);

        return view('filament.bookings.guest-list', [
            ...$roster,
            'showAccessDetails' => $isLead,
            'showHeading' => false,
            'showQualified' => $isLead && $roster['show_qualified'],
            'showKeys' => $isLead && $roster['show_keys'],
            'panel' => 'member-relation-manager',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function withContactAccess(array $groups, Booking $booking, User $actor): array
    {
        return array_map(function (array $group) use ($booking): array {
            $group['people'] = array_map(function (array $person) use ($booking): array {
                $subject = $booking->attendees->find($person['id']);

                return $person + [
                    'can_phone' => $this->canViewContact($subject, 'viewPhone'),
                    'can_emergency' => $this->canViewContact($subject, 'viewEmergencyContact'),
                ];
            }, $group['people']);

            return $group;
        }, $groups);
    }

    private function attendee(User $person): ?User
    {
        return $this->getOwnerRecord()->attendees()->whereKey($person->getKey())->first();
    }

    /**
     * Nothing is offered when the person has not shared the detail, so no notice is shown for nothing.
     */
    private function canViewContact(?User $person, string $ability): bool
    {
        $actor = auth()->user();
        $attendance = $person?->attendance;

        if (! $actor instanceof User || ! $person instanceof User || $attendance === null) {
            return false;
        }

        $shared = $ability === 'viewPhone' ? $person->phone : $person->emergency_phone;

        return filled($shared) && $actor->can($ability, $attendance);
    }

    private function hasAccessDetail(User $person, string $detail): bool
    {
        $booking = $this->getOwnerRecord();
        $actor = auth()->user();

        if (! $booking instanceof Booking || ! $actor instanceof User || ! $booking->isLead($actor)) {
            return false;
        }

        $booking->loadMissing(['attendees', 'team', 'activity.activityAudiences.qualificationRequirements']);
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
