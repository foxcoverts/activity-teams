<?php

namespace App\Filament\Bookings;

use App\Actions\ConfirmAttendanceAction;
use App\Models\Booking;
use App\Models\User;
use App\Support\Avatars;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ConfirmAttendeesAction
{
    public static function make(): Action
    {
        return Action::make('confirmAttendees')
            ->label(__('Confirm attendees'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color(Palette::neutral())
            ->modalHeading(__('Confirm attendees'))
            ->modalWidth(Width::TwoExtraLarge)
            ->extraModalWindowAttributes(['class' => 'confirm-attendees-panel'])
            ->modalSubmitActionLabel(__('Save'))
            ->visible(fn (Booking $record): bool => self::canConfirm($record, auth()->user()))
            ->fillForm(fn (Booking $record): array => self::formState($record))
            ->schema(fn (Booking $record): array => [
                Section::make(__('Needs confirming'))
                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'confirm-attendees-section'])
                    ->visible(fn (Get $get): bool => filled($get('needs_confirming')))
                    ->schema([
                        self::marksRepeater('needs_confirming', defaultMark: null),
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
                Section::make(__('Add someone'))
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('add_user_id')
                            ->label(__('Search people'))
                            ->placeholder(__('Declined or awaiting response'))
                            ->searchable()
                            ->preload(false)
                            ->options(fn (): array => [])
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => self::searchAddable(
                                $record,
                                $search,
                                $get,
                            ))
                            ->getOptionLabelUsing(fn (?string $value): ?string => User::query()->find($value)?->name)
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

                                $set('needs_confirming', $needs->push(self::personRow($person))->values()->all());
                                $set('add_user_id', null);
                            }),
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

                app(ConfirmAttendanceAction::class)($record, $actor, $marks);

                Notification::make()
                    ->success()
                    ->title(__('Attendance updated'))
                    ->send();

                $record->refresh()->unsetRelation('attendees');
                $action->getLivewire()->dispatch('booking-attendance-updated');
            });
    }

    private static function canConfirm(Booking $booking, mixed $user): bool
    {
        if (! $user instanceof User || $booking->isCancelled() || ! $booking->isOnOrAfterSessionDay()) {
            return false;
        }

        $booking->loadMissing('team');

        return $booking->isLead($user)
            || ($booking->team !== null && $user->manages($booking->team));
    }

    /**
     * @return array{needs_confirming: list<array{user_id: string, name: string, avatar_url: string, mark: ?string}>, confirmed: list<array{user_id: string, name: string, avatar_url: string, mark: ?string}>, add_user_id: null}
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
            'add_user_id' => null,
        ];
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
                (string) $attendee->getKey() => $attendee->name,
            ])
            ->all();
    }
}
