<?php

namespace App\Filament\Manage\Resources\Bookings\RelationManagers;

use App\Actions\AddAttendeeAction;
use App\Actions\OverrideAttendanceAction;
use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Support\BookingGuestList;
use App\Support\BookingPeople;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use RuntimeException;

class AttendeesRelationManager extends RelationManager
{
    protected static ?string $title = 'Guest list';

    protected static string $relationship = 'attendees';

    public ?string $inviteSession = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User && $ownerRecord instanceof Booking && $user->can('update', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('qualified')
                    ->label(__('Qualification'))
                    ->badge()
                    ->icon(Heroicon::CheckCircle)
                    ->color(Palette::positive())
                    ->placeholder('')
                    ->state(function (User $record): ?string {
                        $activity = $this->activity();

                        if (! $activity instanceof Activity || ! $activity->metBy($record, $this->booking())) {
                            return null;
                        }

                        return __('Qualified');
                    })
                    ->visible(function (): bool {
                        $activity = $this->activity();

                        return $activity instanceof Activity && $activity->requiresQualification();
                    })
                    ->disabledClick(fn (?string $state): bool => blank($state)),
                TextColumn::make('key_badge')
                    ->label(__('Keys'))
                    ->badge()
                    ->icon(Heroicon::OutlinedKey)
                    ->state(fn (User $record): ?string => $this->keyBadge($record))
                    ->formatStateUsing(fn (?string $state): ?string => match ($state) {
                        'holder' => __('Key holder'),
                        default => null,
                    })
                    ->color(fn (?string $state): array => $state === 'holder' ? Palette::positive() : Palette::neutral())
                    ->visible(fn (): bool => $this->bookingNeedsKeys())
                    ->disabledClick(fn (?string $state): bool => blank($state)),
            ])
            ->content(fn (): View => $this->guestListContent())
            ->paginated(false)
            ->headerActions([
                Action::make('invite')
                    ->visible(fn (): bool => $this->booking()->acceptsInvites())
                    ->modalHeading(__('Invite'))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalWidth(Width::TwoExtraLarge)
                    ->mountUsing(function (): void {
                        $this->inviteSession = (string) Str::ulid();
                    })
                    ->modalContent(fn (): View => view('filament.manage.invite-attendees-host', [
                        'booking' => $this->booking(),
                        'inviteSession' => $this->inviteSession,
                    ])),
                Action::make('add')
                    ->label(__('Add'))
                    ->visible(fn (): bool => ! $this->booking()->isCancelled()
                        && (bool) auth()->user()?->can('create', [BookingAttendance::class, $this->booking()]))
                    ->schema([
                        $this->memberSelect(existing: false),
                        ...$this->attendanceFields(),
                    ])
                    ->action(function (array $data): void {
                        $manager = auth()->user();
                        $response = $this->responseFrom($data);
                        $attendee = User::query()->find($data['user_id'] ?? null);

                        if (! $manager instanceof User || ! $attendee instanceof User || $response === null) {
                            return;
                        }

                        app(AddAttendeeAction::class)(
                            $this->booking(),
                            $attendee,
                            $manager,
                            $response->toStatus(),
                            $data['comment'] ?? null,
                        );
                    }),
            ])
            ->recordActions([
                Action::make('qualifications')
                    ->modalHeading(fn (User $record): string => $record->name)
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->authorize(fn (User $record): bool => $this->isQualified($record))
                    ->modalContent(fn (User $record): View => view('filament.bookings.person-access-host', [
                        'booking' => $this->booking(),
                        'person' => $record,
                        'panel' => 'qualifications',
                    ])),
                Action::make('heldKeys')
                    ->modalHeading(fn (User $record): string => $record->name)
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->authorize(fn (User $record): bool => $this->keyBadge($record) === 'holder')
                    ->modalContent(fn (User $record): View => view('filament.bookings.person-access-host', [
                        'booking' => $this->booking(),
                        'person' => $record,
                        'panel' => 'keys',
                    ])),
                Action::make('editAttendance')
                    ->label(__('Edit'))
                    ->authorize(function (User $record): bool {
                        $attendance = $record->attendance;

                        return $attendance instanceof BookingAttendance
                            && (bool) auth()->user()?->can('override', $attendance);
                    })
                    ->fillForm(fn (User $record): array => [
                        'user_id' => $record->getKey(),
                        'response' => BookingAttendeeResponse::tryFromStatus($record->attendance?->status)?->value,
                        'comment' => $record->attendance?->comment,
                    ])
                    ->schema([
                        $this->memberSelect(existing: true),
                        ...$this->attendanceFields(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $manager = auth()->user();
                        $response = $this->responseFrom($data);

                        if (! $manager instanceof User || $response === null) {
                            return;
                        }

                        app(OverrideAttendanceAction::class)(
                            $this->booking(),
                            $record,
                            $manager,
                            $response->toStatus(),
                            $data['comment'] ?? null,
                        );
                    }),
                DetachAction::make()
                    ->label(__('Remove'))
                    ->authorize(fn (User $record): bool => $this->canRemoveAttendee($record))
                    ->after(function (User $record): void {
                        activity()
                            ->causedBy(auth()->user())
                            ->performedOn($this->booking())
                            ->withProperties(['user_id' => $record->getKey()])
                            ->event('attendee_removed')
                            ->log('Attendee removed');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->authorizeIndividualRecords(fn (User $record): bool => $this->canRemoveAttendee($record)),
                ]),
            ]);
    }

    #[On('invites-sent')]
    #[On('close-invite')]
    public function closeInvite(): void
    {
        $this->unmountTableAction();
    }

    /**
     * @return array<string, string>
     */
    protected function membersNotOnBooking(): array
    {
        $booking = $this->booking();
        $booking->loadMissing('team');

        if ($booking->team === null) {
            return [];
        }

        return $booking->team->members()
            ->where('suspended', false)
            ->whereDoesntHave('bookings', fn (Builder $query) => $query->where('booking_id', $booking->id))
            ->ordered()
            ->pluck('users.name', 'users.id')
            ->all();
    }

    protected function memberSelect(bool $existing): Select
    {
        $select = Select::make('user_id')->label(__('Member'));

        if ($existing) {
            return $select
                ->options(fn (User $record): array => [$record->getKey() => $record->name])
                ->disabled()
                ->dehydrated(false);
        }

        return $select
            ->options(fn (): array => $this->membersNotOnBooking())
            ->searchable()
            ->required();
    }

    /**
     * @return array<int, ToggleButtons|Textarea>
     */
    protected function attendanceFields(): array
    {
        return [
            ToggleButtons::make('response')
                ->label(__('Status'))
                ->options(BookingAttendeeResponse::class)
                ->inline()
                ->required(),
            Textarea::make('comment'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function responseFrom(array $data): ?BookingAttendeeResponse
    {
        $response = $data['response'] ?? null;

        return $response instanceof BookingAttendeeResponse
            ? $response
            : BookingAttendeeResponse::tryFrom((string) $response);
    }

    protected function booking(): Booking
    {
        $booking = $this->getOwnerRecord();

        if (! $booking instanceof Booking) {
            throw new RuntimeException('Attendees belong to a booking.');
        }

        return $booking;
    }

    /** @var array<string, 'holder'|'other'>|null */
    private ?array $keyBadges = null;

    protected function keyBadge(User $record): ?string
    {
        if ($this->keyBadges === null) {
            $booking = $this->booking();
            $booking->loadMissing('attendees');
            $this->keyBadges = BookingPeople::keyBadges($booking, $booking->attendees);
        }

        return $this->keyBadges[$record->getKey()] ?? null;
    }

    protected function bookingNeedsKeys(): bool
    {
        return $this->booking()->requiredKeys()->isNotEmpty();
    }

    protected function activity(): ?Activity
    {
        $this->booking()->loadMissing('activity');

        $activity = $this->booking()->activity;

        return $activity instanceof Activity ? $activity : null;
    }

    protected function isQualified(User $record): bool
    {
        $activity = $this->activity();

        return $activity instanceof Activity
            && $activity->requiresQualification()
            && $activity->metBy($record, $this->booking());
    }

    protected function canRemoveAttendee(User $attendee): bool
    {
        $attendance = $attendee->attendance;

        return $attendance instanceof BookingAttendance
            && (bool) auth()->user()?->can('delete', $attendance);
    }

    protected function guestListContent(): View
    {
        $booking = $this->booking();
        $booking->loadMissing(['attendees', 'team', 'activity.qualificationRequirements']);
        $actor = auth()->user();
        $canManageBooking = $actor instanceof User
            && $booking->team !== null
            && $actor->manages($booking->team);

        $roster = BookingGuestList::forBooking(
            $booking,
            $booking->attendees,
            BookingAttendeeStatus::cases(),
            includeSuspended: true,
            includeAccessDetails: true,
        );

        $roster['groups'] = array_map(
            function (array $group) use ($booking, $canManageBooking): array {
                $group['people'] = array_map(
                    function (array $person) use ($booking, $canManageBooking): array {
                        $attendee = $booking->attendees->first(
                            fn (User $attendee): bool => (string) $attendee->getKey() === $person['id'],
                        );
                        $attendance = $attendee?->attendance;

                        $person['can_edit'] = $canManageBooking
                            && $attendee instanceof User
                            && ! $booking->isCancelled()
                            && ! $attendee->isSuspended();
                        $person['can_remove'] = $canManageBooking
                            && $attendee instanceof User
                            && $attendance instanceof BookingAttendance
                            && ! ($booking->isLead($attendee) && $attendance->isAccepted());

                        return $person;
                    },
                    $group['people'],
                );

                return $group;
            },
            $roster['groups'],
        );

        return view('filament.bookings.guest-list', [
            'groups' => $roster['groups'],
            'showQualified' => $roster['show_qualified'],
            'showKeys' => $roster['show_keys'],
            'panel' => 'manage',
            'showAccessDetails' => true,
            'showHeading' => false,
        ]);
    }
}
