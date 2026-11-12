<?php

namespace App\Filament\Teams\Resources\Bookings\RelationManagers;

use App\Actions\AddAttendeeAction;
use App\Actions\OverrideAttendanceAction;
use App\Actions\ResendBookingIcsAction;
use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Support\BookingGuestList;
use App\Support\BookingPeople;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
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
        $actor = auth()->user();

        if (! $ownerRecord instanceof Booking || ! $actor instanceof User) {
            return false;
        }

        $ownerRecord->loadMissing('team');

        if ($ownerRecord->team === null) {
            return false;
        }

        return $actor->isCurrentMemberOf($ownerRecord->team)
            || $actor->can('update', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([])
            ->content(fn (): View => $this->guestListContent())
            ->paginated(false)
            ->headerActions([
                ActionGroup::make([
                    Action::make('addToCalendar')
                        ->label(__('Add to Calendar'))
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->url(fn (): string => route('booking.show.ics', $this->booking()))
                        ->openUrlInNewTab(),
                    Action::make('sendToEmail')
                        ->label(__('Send to Email'))
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->action(function (): void {
                            $user = auth()->user();

                            if (! $user instanceof User) {
                                return;
                            }

                            app(ResendBookingIcsAction::class)($this->booking(), $user, force: true);

                            Notification::make()
                                ->success()
                                ->title(__('Booking details have been sent to your email address.'))
                                ->send();
                        }),
                ])
                    ->label(__('Calendar'))
                    ->hiddenLabel()
                    ->tooltip(__('Calendar'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->button()
                    ->visible(fn (): bool => $this->canUseCalendarActions()),
                ActionGroup::make([
                    Action::make('invite')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->visible(fn (): bool => $this->canManageBooking() && $this->booking()->acceptsInvites())
                        ->modalHeading(__('Invite'))
                        ->modalSubmitAction(false)
                        ->modalCancelAction(false)
                        ->modalWidth(Width::TwoExtraLarge)
                        ->mountUsing(function (): void {
                            $this->inviteSession = (string) Str::ulid();
                        })
                        ->modalContent(fn (): View => view('filament.bookings.invite-attendees-host', [
                            'booking' => $this->booking(),
                            'inviteSession' => $this->inviteSession,
                        ])),
                    Action::make('add')
                        ->label(__('Add'))
                        ->icon(Heroicon::OutlinedUserPlus)
                        ->visible(fn (): bool => $this->canManageBooking()
                            && ! $this->booking()->isCancelled()
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

                            $this->attendanceChanged();
                        }),
                ])
                    ->label(__('People'))
                    ->hiddenLabel()
                    ->tooltip(__('People'))
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->button()
                    ->color(fn (): ?array => $this->booking()->isPast() ? Palette::neutral() : null)
                    ->visible(fn (): bool => $this->canManageBooking()
                        && ($this->booking()->acceptsInvites()
                            || (! $this->booking()->isCancelled()
                                && (bool) auth()->user()?->can('create', [BookingAttendance::class, $this->booking()])))),
            ])
            ->recordActions([
                Action::make('qualifications')
                    ->modalHeading(fn (User $record): string => $record->name)
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->authorize(fn (User $record): bool => $this->canManageBooking()
                        ? $this->isQualified($record)
                        : $this->hasAccessDetail($record, 'qualified'))
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
                    ->authorize(fn (User $record): bool => $this->canManageBooking()
                        ? $this->keyBadge($record) === 'holder'
                        : $this->hasAccessDetail($record, 'key_holder'))
                    ->modalContent(fn (User $record): View => view('filament.bookings.person-access-host', [
                        'booking' => $this->booking(),
                        'person' => $record,
                        'panel' => 'keys',
                    ])),
                Action::make('contact')
                    ->label(__('Contact'))
                    ->icon(Heroicon::OutlinedPhoneArrowUpRight)
                    ->modalHeading(fn (User $record): HtmlString => new HtmlString(
                        view('filament.bookings.contact-modal-heading', ['person' => $record])->render(),
                    ))
                    ->modalWidth(Width::Large)
                    ->extraModalWindowAttributes(['class' => 'booking-contact-panel'])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->extraModalFooterActions(fn (User $record): array => [
                        Action::make('viewMember')
                            ->label(__('View member'))
                            ->icon(Heroicon::OutlinedUser)
                            ->color('gray')
                            ->url(fn (): string => MemberResource::getUrl(
                                'view',
                                ['record' => $record],
                                panel: 'teams',
                                tenant: $this->booking()->team,
                            ))
                            ->visible(fn (): bool => MemberResource::canViewAny()
                                && MemberResource::canView($record)),
                    ])
                    ->authorize(fn (User $record): bool => $this->canOpenContact($this->attendee($record)))
                    ->modalContent(fn (User $record): View => view('filament.bookings.contact-details-host', [
                        'booking' => $this->booking(),
                        'person' => $record,
                    ])),
                Action::make('editAttendance')
                    ->label(__('Edit'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (): bool => $this->canManageBooking())
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

                        $this->attendanceChanged();
                    }),
                DetachAction::make()
                    ->label(__('Remove'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->visible(fn (): bool => $this->canManageBooking())
                    ->authorize(fn (User $record): bool => $this->canRemoveAttendee($record))
                    ->after(function (User $record): void {
                        activity()
                            ->causedBy(auth()->user())
                            ->performedOn($this->booking())
                            ->withProperties(['user_id' => $record->getKey()])
                            ->event('attendee_removed')
                            ->log('Attendee removed');

                        $this->attendanceChanged();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->visible(fn (): bool => $this->canManageBooking())
                        ->authorizeIndividualRecords(fn (User $record): bool => $this->canRemoveAttendee($record))
                        ->after(function (): void {
                            $this->attendanceChanged();
                        }),
                ]),
            ]);
    }

    #[On('booking-attendance-updated')]
    public function refreshAfterAttendanceChange(): void {}

    #[On('invites-sent')]
    public function invitesSent(): void
    {
        $this->closeInvite();
        $this->attendanceChanged();
    }

    #[On('close-invite')]
    public function closeInvite(): void
    {
        $this->unmountTableAction();
    }

    private function attendanceChanged(): void
    {
        $this->booking()->unsetRelation('attendees');
        $this->dispatch('booking-attendance-updated');
    }

    private function canManageBooking(): bool
    {
        $actor = auth()->user();
        $booking = $this->booking();

        return $actor instanceof User && $actor->can('update', $booking);
    }

    private function canUseCalendarActions(): bool
    {
        $actor = auth()->user();
        $booking = $this->booking();

        if (! $actor instanceof User || ! $booking->acceptsInvites()) {
            return false;
        }

        $attendee = $booking->attendees()->whereKey($actor->getKey())->first();
        $status = $attendee?->attendance?->status;

        return in_array($status, [
            BookingAttendeeStatus::NeedsAction,
            BookingAttendeeStatus::Accepted,
            BookingAttendeeStatus::Tentative,
        ], true);
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

        $booking->loadMissing('attendees');

        $onBookingIds = $booking->attendees
            ->reject(fn (User $attendee): bool => $attendee->attendance?->isEligibleForReinvite($booking) ?? false)
            ->map(fn (User $attendee): string => (string) $attendee->getKey())
            ->all();

        return $booking->team->members()
            ->where('suspended', false)
            ->when(
                $onBookingIds !== [],
                fn (Builder $query) => $query->whereKeyNot($onBookingIds),
            )
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
        return $this->booking()->requiredLocks()->isNotEmpty();
    }

    protected function isQualified(User $record): bool
    {
        $booking = $this->booking();

        return $booking->requiresQualification() && $booking->metBy($record);
    }

    protected function canRemoveAttendee(User $attendee): bool
    {
        $attendance = $attendee->attendance;

        return $attendance instanceof BookingAttendance
            && (bool) auth()->user()?->can('delete', $attendance);
    }

    protected function guestListContent(): View
    {
        if ($this->canManageBooking()) {
            return $this->managerGuestListContent();
        }

        return $this->memberGuestListContent();
    }

    protected function managerGuestListContent(): View
    {
        $booking = $this->booking();
        $booking->loadMissing(['attendees', 'team', 'activity.activityAudiences.qualificationRequirements']);
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
                        $person['can_phone'] = $this->canViewContact($attendee, 'viewPhone');
                        $person['can_emergency'] = $this->canViewContact($attendee, 'viewEmergencyContact');
                        $person['has_contact_details'] = $this->hasSharedContactDetails($attendee);

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
            'panel' => 'teams',
            'showAccessDetails' => true,
            'showHeading' => false,
        ]);
    }

    private function memberGuestListContent(): View
    {
        $booking = $this->booking();
        $actor = auth()->user();

        if (! $actor instanceof User) {
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

        $roster['groups'] = $this->withContactAccess($roster['groups'], $booking);

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
    private function withContactAccess(array $groups, Booking $booking): array
    {
        return array_map(function (array $group) use ($booking): array {
            $group['people'] = array_map(function (array $person) use ($booking): array {
                $subject = $booking->attendees->find($person['id']);

                return $person + [
                    'can_phone' => $this->canViewContact($subject, 'viewPhone'),
                    'can_emergency' => $this->canViewContact($subject, 'viewEmergencyContact'),
                    'has_contact_details' => $this->hasSharedContactDetails($subject),
                ];
            }, $group['people']);

            return $group;
        }, $groups);
    }

    private function attendee(User $person): ?User
    {
        return $this->getOwnerRecord()->attendees()->whereKey($person->getKey())->first();
    }

    private function canOpenContact(?User $person): bool
    {
        return $this->canViewContact($person, 'viewPhone')
            || $this->canViewContact($person, 'viewEmergencyContact');
    }

    private function hasSharedContactDetails(?User $person): bool
    {
        if (! $person instanceof User) {
            return false;
        }

        return $this->canViewContact($person, 'viewPhone')
            || ($this->canViewContact($person, 'viewEmergencyContact') && filled($person->emergency_phone));
    }

    private function canViewContact(?User $person, string $ability): bool
    {
        $actor = auth()->user();
        $attendance = $person?->attendance;

        if (! $actor instanceof User || ! $person instanceof User || $attendance === null) {
            return false;
        }

        if (! $actor->can($ability, $attendance)) {
            return false;
        }

        // Phone: only offer the channel when a number exists. Emergency: allow the
        // empty tip path when the policy window/role allows it.
        if ($ability === 'viewPhone') {
            return filled($person->phone);
        }

        return true;
    }

    private function hasAccessDetail(User $person, string $detail): bool
    {
        $booking = $this->booking();
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
