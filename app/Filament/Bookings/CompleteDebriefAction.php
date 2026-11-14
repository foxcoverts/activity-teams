<?php

namespace App\Filament\Bookings;

use App\Actions\CompleteDebriefAction as CompleteDebrief;
use App\Actions\ConfirmAttendanceAction;
use App\Enums\TeamFeature;
use App\Models\Booking;
use App\Models\BookingDebrief;
use App\Models\EquipmentSet;
use App\Models\EquipmentSetGroup;
use App\Models\User;
use App\Support\Avatars;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class CompleteDebriefAction
{
    public static function make(): Action
    {
        return Action::make('completeDebrief')
            ->label(__('Complete debrief'))
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color(Palette::positive())
            ->modalHeading(__('Session debrief'))
            ->modalWidth(Width::TwoExtraLarge)
            ->extraModalWindowAttributes(['class' => 'confirm-attendees-panel'])
            ->modalSubmitActionLabel(__('Save debrief'))
            ->visible(fn (Booking $record): bool => self::isAvailable($record, auth()->user()))
            ->fillForm(fn (Booking $record): array => self::formState($record))
            ->schema(fn (Booking $record): array => [
                Section::make(__('Session'))
                    ->icon(Heroicon::OutlinedClock)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('hours')
                            ->label(__('Operating hours'))
                            ->numeric()
                            ->step(0.25)
                            ->minValue(0)
                            ->maxValue(999)
                            ->inputMode('decimal')
                            ->required(),
                        TextInput::make('participant_count')
                            ->label(__('Participant numbers'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->required(),
                        TextInput::make('weather')
                            ->label(__('Weather'))
                            ->maxLength(255)
                            ->required(),
                        TextInput::make('notes')
                            ->label(__('Notes'))
                            ->maxLength(255),
                    ]),
                Section::make(__('Equipment used'))
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes(fn (): array => [
                        'class' => self::equipmentUsedSectionClass($record),
                    ])
                    ->visible(fn (): bool => self::teamHasEquipmentSets($record))
                    ->schema(fn (): array => self::equipmentUsedSchema($record)),
                Section::make(__('Confirm attendance'))
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'confirm-attendees-section'])
                    ->headerActions([
                        Action::make('revealAddPerson')
                            ->label(__('Add'))
                            ->icon(Heroicon::Plus)
                            ->link()
                            ->visible(fn (Get $get): bool => ! (bool) $get('show_add_person'))
                            ->action(function (Set $set): void {
                                $set('show_add_person', true);
                            }),
                    ])
                    ->schema([
                        Hidden::make('show_add_person')
                            ->default(false)
                            ->dehydrated(false),
                        self::marksRepeater('needs_confirming', defaultMark: null)
                            ->visible(fn (Get $get): bool => filled($get('needs_confirming'))),
                        Select::make('add_user_id')
                            ->label(__('Add attendee'))
                            ->placeholder(__('Declined or awaiting response'))
                            ->searchable()
                            ->preload(false)
                            ->allowHtml()
                            ->native(false)
                            ->options(fn (): array => [])
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => self::searchAddable(
                                $record,
                                $search,
                                $get,
                            ))
                            ->getOptionLabelUsing(function (?string $value): ?string {
                                $person = filled($value) ? User::query()->find($value) : null;

                                return $person instanceof User
                                    ? self::personOptionLabel($person)
                                    : null;
                            })
                            ->visible(fn (Get $get): bool => (bool) $get('show_add_person'))
                            ->live()
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (! filled($state)) {
                                    return;
                                }

                                $person = User::query()->find($state);

                                if (! $person instanceof User) {
                                    return;
                                }

                                $needs = collect($get('needs_confirming') ?? []);
                                $confirmed = collect($get('confirmed') ?? []);

                                if (
                                    $needs->contains(fn (array $row): bool => (string) ($row['user_id'] ?? '') === (string) $state)
                                    || $confirmed->contains(fn (array $row): bool => (string) ($row['user_id'] ?? '') === (string) $state)
                                ) {
                                    $set('add_user_id', null);

                                    return;
                                }

                                $set(
                                    'needs_confirming',
                                    $needs->push(self::personRow(
                                        $person,
                                        ConfirmAttendanceAction::MARK_CONFIRM,
                                    ))->values()->all(),
                                );
                                $set('add_user_id', null);
                            })
                            ->extraFieldWrapperAttributes(['class' => 'confirm-attendees-add']),
                    ]),
                Section::make(__('Confirmed'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'confirm-attendees-section'])
                    ->visible(fn (Get $get): bool => filled($get('confirmed')))
                    ->schema([
                        self::marksRepeater('confirmed', defaultMark: ConfirmAttendanceAction::MARK_CONFIRM),
                    ]),
            ])
            ->action(function (Booking $record, array $data, Action $action): void {
                $actor = auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                $marks = [
                    ...self::marksFromRows($data['needs_confirming'] ?? [], ConfirmAttendanceAction::MARK_SKIP),
                    ...self::marksFromRows($data['confirmed'] ?? [], ConfirmAttendanceAction::MARK_CONFIRM),
                ];

                app(CompleteDebrief::class)($record, $actor, [
                    'hours' => $data['hours'] ?? null,
                    'participant_count' => $data['participant_count'] ?? null,
                    'weather' => $data['weather'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'equipment_set_ids' => self::equipmentSetIdsFromForm($data),
                    'marks' => $marks,
                ]);

                Notification::make()
                    ->success()
                    ->title(__('Debrief saved'))
                    ->send();

                $record->refresh()->unsetRelation('attendees')->unsetRelation('debrief');
                $action->getLivewire()->dispatch('booking-attendance-updated');
            });
    }

    public static function isAvailable(Booking $booking, mixed $user): bool
    {
        if (! $user instanceof User || $booking->isCancelled() || ! $booking->isOnOrAfterSessionDay()) {
            return false;
        }

        $booking->loadMissing(['team', 'debrief']);

        if ($booking->team === null || ! $booking->team->featureEnabled(TeamFeature::Debrief)) {
            return false;
        }

        if ($booking->debrief instanceof BookingDebrief) {
            return false;
        }

        return $booking->isLead($user)
            || $user->manages($booking->team);
    }

    /**
     * @return array{
     *     needs_confirming: list<array{user_id: string, name: string, avatar_url: string, mark: ?string}>,
     *     confirmed: list<array{user_id: string, name: string, avatar_url: string, mark: ?string}>,
     *     hours: ?float,
     *     participant_count: null,
     *     weather: null,
     *     notes: null,
     *     equipment_set_ids_by_group: array<string, list<string>>,
     *     show_add_person: false,
     *     add_user_id: null
     * }
     */
    private static function formState(Booking $booking): array
    {
        $booking->loadMissing('attendees');

        $needs = [];
        $confirmed = [];

        foreach ($booking->attendees->sortBy(fn (User $user): string => $user->name) as $attendee) {
            $attendance = $attendee->attendance;

            if ($attendance === null) {
                continue;
            }

            if ($attendance->isAccepted() && $attendance->isAttendanceConfirmed()) {
                $confirmed[] = self::personRow($attendee, ConfirmAttendanceAction::MARK_CONFIRM);

                continue;
            }

            if ($attendance->isAccepted() || $attendance->isTentative()) {
                $needs[] = self::personRow($attendee);
            }
        }

        return [
            'needs_confirming' => $needs,
            'confirmed' => $confirmed,
            'hours' => self::suggestedOperatingHours($booking),
            'participant_count' => null,
            'weather' => null,
            'notes' => null,
            'equipment_set_ids_by_group' => self::emptyEquipmentSetIdsByGroup($booking),
            'show_add_person' => false,
            'add_user_id' => null,
        ];
    }

    private static function suggestedOperatingHours(Booking $booking): ?float
    {
        if ($booking->start_at === null || $booking->end_at === null) {
            return null;
        }

        $hours = $booking->start_at->floatDiffInHours($booking->end_at);

        if ($hours <= 0) {
            return null;
        }

        return round($hours, 2);
    }

    /**
     * @return array{user_id: string, name: string, avatar_url: string, mark: ?string}
     */
    private static function personRow(User $person, ?string $mark = null): array
    {
        return [
            'user_id' => (string) $person->getKey(),
            'name' => $person->name,
            'avatar_url' => Avatars::userUrl($person, 64),
            'mark' => $mark,
        ];
    }

    private static function marksRepeater(string $name, ?string $defaultMark): Repeater
    {
        $options = [
            ConfirmAttendanceAction::MARK_CONFIRM => __('Attended'),
            ConfirmAttendanceAction::MARK_DECLINE => __('Did not attend'),
        ];

        $icons = [
            ConfirmAttendanceAction::MARK_CONFIRM => Heroicon::Check,
            ConfirmAttendanceAction::MARK_DECLINE => Heroicon::XMark,
        ];

        $colors = [
            ConfirmAttendanceAction::MARK_CONFIRM => Palette::positive(),
            ConfirmAttendanceAction::MARK_DECLINE => Palette::destructive(),
        ];

        return Repeater::make($name)
            ->hiddenLabel()
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->defaultItems(0)
            ->extraAttributes(['class' => 'confirm-attendees-rollcall'])
            ->schema([
                Hidden::make('user_id'),
                Hidden::make('name'),
                Hidden::make('avatar_url'),
                ToggleButtons::make('mark')
                    ->hiddenLabel()
                    ->options($options)
                    ->icons($icons)
                    ->colors($colors)
                    ->tooltips($options)
                    ->hiddenButtonLabels()
                    ->size(Size::Small)
                    ->inline()
                    ->grouped()
                    ->default($defaultMark)
                    ->extraFieldWrapperAttributes(['class' => 'confirm-attendees-mark']),
                Placeholder::make('person')
                    ->hiddenLabel()
                    ->content(fn (Get $get): HtmlString => new HtmlString(
                        '<span class="invite-person">'
                        .view('filament.bookings.person-identity', [
                            'person' => [
                                'name' => (string) ($get('name') ?? ''),
                                'avatar_url' => (string) ($get('avatar_url') ?? ''),
                            ],
                        ])->render()
                        .'</span>'
                    ))
                    ->extraAttributes(['class' => 'confirm-attendees-person']),
            ])
            ->columnSpanFull();
    }

    /**
     * @param  list<mixed>  $rows
     * @return array<string, string>
     */
    private static function marksFromRows(array $rows, string $default): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['user_id'] ?? null))
            ->mapWithKeys(function (array $row) use ($default): array {
                $mark = $row['mark'] ?? null;

                if (! is_string($mark) || $mark === '') {
                    $mark = $default;
                }

                return [(string) $row['user_id'] => $mark];
            })
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function searchAddable(Booking $booking, string $search, Get $get): array
    {
        $listed = collect([
            ...($get('needs_confirming') ?? []),
            ...($get('confirmed') ?? []),
        ])
            ->pluck('user_id')
            ->filter()
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        $booking->loadMissing(['attendees', 'team']);

        $fromBooking = $booking->attendees
            ->filter(function (User $attendee) use ($listed): bool {
                if (in_array((string) $attendee->getKey(), $listed, true)) {
                    return false;
                }

                $attendance = $attendee->attendance;

                return $attendance !== null
                    && ($attendance->isDeclined() || $attendance->needsAction());
            });

        $fromTeam = $booking->team === null
            ? collect()
            : $booking->team->members()
                ->where('suspended', false)
                ->whereKeyNot([
                    ...$listed,
                    ...$booking->attendees->map(fn (User $user): string => (string) $user->getKey())->all(),
                ])
                ->get();

        return $fromBooking
            ->concat($fromTeam)
            ->unique(fn (User $attendee): string => (string) $attendee->getKey())
            ->filter(fn (User $attendee): bool => str_contains(
                mb_strtolower($attendee->name),
                mb_strtolower($search),
            ))
            ->sortBy(fn (User $attendee): string => $attendee->name)
            ->take(20)
            ->mapWithKeys(fn (User $attendee): array => [
                (string) $attendee->getKey() => self::personOptionLabel($attendee),
            ])
            ->all();
    }

    private static function personOptionLabel(User $person): string
    {
        return '<span class="inline-flex items-center gap-2">'
            .view('filament.bookings.person-identity', [
                'person' => [
                    'name' => $person->name,
                    'avatar_url' => Avatars::userUrl($person, 64),
                ],
            ])->render()
            .'</span>';
    }

    private static function teamHasEquipmentSets(Booking $booking): bool
    {
        $booking->loadMissing('team');

        if ($booking->team === null) {
            return false;
        }

        return $booking->team->equipmentSets()->exists();
    }

    private static function equipmentUsedSectionClass(Booking $booking): string
    {
        $classes = 'complete-debrief-equipment';

        if (self::eligibleEquipmentGroups($booking)->count() <= 1) {
            $classes .= ' complete-debrief-equipment--flat';
        }

        return $classes;
    }

    /**
     * @return list<Component>
     */
    private static function equipmentUsedSchema(Booking $booking): array
    {
        $grouped = self::eligibleEquipmentGroups($booking);

        if ($grouped->count() <= 1) {
            $groupId = (string) $grouped->keys()->first();
            /** @var Collection<int, EquipmentSet> $sets */
            $sets = $grouped->first() ?? collect();

            return [
                self::equipmentSetCheckboxList($groupId, $sets),
            ];
        }

        return $grouped
            ->map(function (Collection $sets, string $groupId): Fieldset {
                /** @var EquipmentSet $first */
                $first = $sets->first();
                $label = $first->equipmentSetGroup?->label
                    ?? EquipmentSetGroup::DEFAULT_LABEL;

                return Fieldset::make($label)
                    ->columns(1)
                    ->schema([
                        self::equipmentSetCheckboxList($groupId, $sets),
                    ]);
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, EquipmentSet>  $sets
     */
    private static function equipmentSetCheckboxList(string $groupId, Collection $sets): CheckboxList
    {
        return CheckboxList::make('equipment_set_ids_by_group.'.$groupId)
            ->hiddenLabel()
            ->options(
                $sets
                    ->mapWithKeys(fn (EquipmentSet $set): array => [
                        (string) $set->getKey() => $set->name,
                    ])
                    ->all(),
            )
            ->columns(1);
    }

    /**
     * @return Collection<string, Collection<int, EquipmentSet>>
     */
    private static function eligibleEquipmentGroups(Booking $booking): Collection
    {
        return self::eligibleEquipmentSets($booking)
            ->groupBy(fn (EquipmentSet $set): string => (string) $set->equipment_set_group_id);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function emptyEquipmentSetIdsByGroup(Booking $booking): array
    {
        return self::eligibleEquipmentGroups($booking)
            ->map(fn (): array => [])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function equipmentSetIdsFromForm(array $data): array
    {
        $grouped = $data['equipment_set_ids_by_group'] ?? [];

        if (! is_array($grouped)) {
            return [];
        }

        return collect($grouped)
            ->flatten()
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, EquipmentSet>
     */
    private static function eligibleEquipmentSets(Booking $booking): Collection
    {
        $booking->loadMissing(['team', 'activity']);

        if ($booking->team === null) {
            return collect();
        }

        $query = EquipmentSet::query()
            ->with('equipmentSetGroup')
            ->where('equipment_sets.team_id', $booking->team->getKey())
            ->join('equipment_set_groups', 'equipment_sets.equipment_set_group_id', '=', 'equipment_set_groups.id')
            ->orderBy('equipment_set_groups.rank')
            ->orderBy('equipment_sets.name')
            ->select('equipment_sets.*');

        if (filled($booking->activity_id)) {
            $query->whereHas(
                'activities',
                fn ($activities) => $activities->whereKey($booking->activity_id),
            );
        }

        return $query->get();
    }
}
