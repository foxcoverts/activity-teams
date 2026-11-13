<?php

namespace App\Filament\Bookings;

use App\Actions\CompleteDebriefAction as CompleteDebrief;
use App\Actions\ConfirmAttendanceAction;
use App\Enums\TeamFeature;
use App\Models\Booking;
use App\Models\BookingDebrief;
use App\Models\EquipmentSet;
use App\Models\User;
use App\Support\Avatars;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class CompleteDebriefAction
{
    public static function make(): Action
    {
        return Action::make('completeDebrief')
            ->label(__('Complete debrief'))
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->color(Palette::positive())
            ->modalHeading(__('Complete debrief'))
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
                            ->inputMode('decimal'),
                        TextInput::make('participant_count')
                            ->label(__('Participant numbers'))
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999),
                        TextInput::make('weather')
                            ->label(__('Weather'))
                            ->maxLength(255),
                        TextInput::make('notes')
                            ->label(__('Notes'))
                            ->maxLength(255),
                    ]),
                Section::make(__('Equipment used'))
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->columns(1)
                    ->columnSpanFull()
                    ->visible(fn (): bool => self::teamHasEquipmentSets($record))
                    ->schema([
                        CheckboxList::make('equipment_set_ids')
                            ->hiddenLabel()
                            ->options(fn (): array => self::equipmentSetOptions($record))
                            ->columns(1),
                    ]),
                Section::make(__('Needs confirming'))
                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'confirm-attendees-section'])
                    ->visible(fn (Get $get): bool => filled($get('needs_confirming')))
                    ->schema([
                        self::marksRepeater('needs_confirming', defaultMark: ConfirmAttendanceAction::MARK_CONFIRM),
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
                    ...self::marksFromRows($data['needs_confirming'] ?? []),
                    ...self::marksFromRows($data['confirmed'] ?? []),
                ];

                app(CompleteDebrief::class)($record, $actor, [
                    'hours' => $data['hours'] ?? null,
                    'participant_count' => $data['participant_count'] ?? null,
                    'weather' => $data['weather'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'equipment_set_ids' => array_values(array_filter(
                        $data['equipment_set_ids'] ?? [],
                        fn (mixed $id): bool => is_string($id) && $id !== '',
                    )),
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
     *     needs_confirming: list<array{user_id: string, name: string, avatar_url: string, mark: string}>,
     *     confirmed: list<array{user_id: string, name: string, avatar_url: string, mark: string}>,
     *     hours: null,
     *     participant_count: null,
     *     weather: null,
     *     notes: null,
     *     equipment_set_ids: list<string>
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
                $needs[] = self::personRow($attendee, ConfirmAttendanceAction::MARK_CONFIRM);
            }
        }

        return [
            'needs_confirming' => $needs,
            'confirmed' => $confirmed,
            'hours' => null,
            'participant_count' => null,
            'weather' => null,
            'notes' => null,
            'equipment_set_ids' => [],
        ];
    }

    /**
     * @return array{user_id: string, name: string, avatar_url: string, mark: string}
     */
    private static function personRow(User $person, string $mark): array
    {
        return [
            'user_id' => (string) $person->getKey(),
            'name' => $person->name,
            'avatar_url' => Avatars::userUrl($person, 64),
            'mark' => $mark,
        ];
    }

    private static function marksRepeater(string $name, string $defaultMark): Repeater
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
                    ->required()
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
    private static function marksFromRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['user_id'] ?? null))
            ->mapWithKeys(function (array $row): array {
                $mark = $row['mark'] ?? ConfirmAttendanceAction::MARK_CONFIRM;

                if (! is_string($mark) || $mark === '') {
                    $mark = ConfirmAttendanceAction::MARK_CONFIRM;
                }

                return [(string) $row['user_id'] => $mark];
            })
            ->all();
    }

    private static function teamHasEquipmentSets(Booking $booking): bool
    {
        $booking->loadMissing('team');

        if ($booking->team === null) {
            return false;
        }

        return $booking->team->equipmentSets()->exists();
    }

    /**
     * @return array<string, string>
     */
    private static function equipmentSetOptions(Booking $booking): array
    {
        $booking->loadMissing(['team', 'activity']);

        if ($booking->team === null) {
            return [];
        }

        $query = EquipmentSet::query()
            ->where('team_id', $booking->team->getKey())
            ->orderBy('name');

        if (filled($booking->activity_id)) {
            $query->whereHas(
                'activities',
                fn ($activities) => $activities->whereKey($booking->activity_id),
            );
        }

        return $query
            ->get()
            ->mapWithKeys(fn (EquipmentSet $set): array => [
                (string) $set->getKey() => $set->name,
            ])
            ->all();
    }
}
