<?php

namespace App\Filament\Teams\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Venues\VenueLocationSchema;
use App\Models\Activity;
use App\Models\Audience;
use App\Models\Booking;
use App\Models\Team;
use App\Models\Venue;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use Livewire\Component as LivewireComponent;

class BookingForm
{
    public const OTHER_ACTIVITY = 'other';

    public const OTHER_VENUE = 'other';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn (?Booking $record): string => __(':Status booking', [
                    'status' => $record?->status->getLabel() ?? __('New'),
                ]))
                    ->icon(fn (?Booking $record): Heroicon => ($record?->status ?? BookingStatus::Tentative)->getIcon())
                    ->iconColor(fn (?Booking $record): array => ($record?->status ?? BookingStatus::Tentative)->getColor())
                    ->columnSpanFull()
                    ->schema([
                        ...static::detailComponents(),
                    ]),
            ]);
    }

    public static function detailComponents(): array
    {
        return [
            Select::make('activity_id')
                ->label(__('Activity'))
                ->options(fn (): array => static::activityOptions())
                ->default(fn (): ?string => static::teamDefault('activities'))
                ->searchable()
                ->selectablePlaceholder(false)
                ->visible(fn (): bool => static::teamHasCatalogActivities())
                ->live()
                ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                    $set('activity_label', null);
                    $set('show_activity_label', false);

                    if ($state === self::OTHER_ACTIVITY) {
                        $set('audience_id', null);

                        return;
                    }

                    $offered = static::audienceOptions(is_string($state) ? $state : null);
                    $current = $get('audience_id');

                    if (is_string($current) && array_key_exists($current, $offered)) {
                        return;
                    }

                    $set('audience_id', static::defaultAudienceId(is_string($state) ? $state : null));
                })
                ->required(fn (): bool => static::teamHasCatalogActivities())
                ->suffixAction(
                    Action::make('showAs')
                        ->label(__('Set activity name'))
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->tooltip(__('Use a different name for this booking.'))
                        ->visible(fn (Get $get): bool => static::teamHasCatalogActivities()
                            && $get('activity_id') !== self::OTHER_ACTIVITY
                            && ! static::activityRequiresLabel(is_string($get('activity_id')) ? $get('activity_id') : null)
                            && ! $get('show_activity_label'))
                        ->action(function (Set $set, LivewireComponent $livewire): void {
                            $set('show_activity_label', true);

                            $livewire->js(
                                'setTimeout(() => document.getElementById('.Js::from('booking-activity-label')->toHtml().')?.focus(), 50)',
                            );
                        }),
                ),
            Hidden::make('show_activity_label')
                ->default(false)
                ->dehydrated(false),
            TextInput::make('activity_label')
                ->label(__('Activity name'))
                ->id('booking-activity-label')
                ->placeholder(function (Get $get): ?string {
                    $activityId = $get('activity_id');

                    if ($activityId === self::OTHER_ACTIVITY || ! static::teamHasCatalogActivities()) {
                        return __('Enter an activity name');
                    }

                    return static::activityOptions()[(string) $activityId] ?? null;
                })
                ->maxLength(255)
                ->live(onBlur: true)
                ->visible(fn (Get $get): bool => ! static::teamHasCatalogActivities()
                    || $get('activity_id') === self::OTHER_ACTIVITY
                    || static::activityRequiresLabel(is_string($get('activity_id')) ? $get('activity_id') : null)
                    || (bool) $get('show_activity_label'))
                ->required(fn (Get $get): bool => ! static::teamHasCatalogActivities()
                    || $get('activity_id') === self::OTHER_ACTIVITY
                    || static::activityRequiresLabel(is_string($get('activity_id')) ? $get('activity_id') : null)),
            Select::make('audience_id')
                ->label(__('Audience'))
                ->options(fn (Get $get): array => static::audienceOptions(is_string($get('activity_id')) ? $get('activity_id') : null))
                ->default(fn (): ?string => static::defaultAudienceId(static::teamDefault('activities')))
                ->visible(fn (Get $get): bool => static::teamHasCatalogActivities()
                    && $get('activity_id') !== self::OTHER_ACTIVITY)
                ->required(fn (Get $get): bool => static::teamHasCatalogActivities()
                    && $get('activity_id') !== self::OTHER_ACTIVITY),
            TextInput::make('group_name')
                ->maxLength(255),
            Grid::make(['default' => 1, 'sm' => 6])
                ->key('schedule_groups')
                ->schema([
                    FusedGroup::make([
                        DatePicker::make('start_date')
                            ->label(__('Date'))
                            ->default(fn (): string => static::defaultScheduleStart()->toDateString())
                            ->live()
                            ->afterStateUpdated(function (mixed $state, mixed $old, Set $set, Get $get): void {
                                static::shiftEndToKeepDuration($state, $old, true, $set, $get);
                            })
                            ->required()
                            ->columnSpan(['sm' => 2]),
                        TimePicker::make('start_time')
                            ->label(__('Start'))
                            ->seconds(false)
                            ->default(fn (): string => static::defaultScheduleStart()->format('H:i'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (mixed $state, mixed $old, Set $set, Get $get): void {
                                static::shiftEndToKeepDuration($state, $old, false, $set, $get);
                            })
                            ->required()
                            ->columnSpan(['sm' => 1]),
                    ])
                        ->key('start_schedule')
                        ->label(__('Start'))
                        ->columns(['default' => 1, 'sm' => 3])
                        ->columnSpan(['default' => 1, 'sm' => 3]),
                    FusedGroup::make([
                        TimePicker::make('end_time')
                            ->label(__('End'))
                            ->seconds(false)
                            ->default(fn (): string => static::defaultScheduleEnd()->format('H:i'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (mixed $state, mixed $old, Set $set, Get $get): void {
                                static::updateEndTime($state, $old, $set, $get);
                            })
                            ->required()
                            ->columnSpan(['sm' => 1]),
                        DatePicker::make('end_date')
                            ->label(__('Date'))
                            ->default(fn (): string => static::defaultScheduleStart()->toDateString())
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                                static::updateEndDate($state, $set, $get);
                            })
                            ->minDate(fn (Get $get): mixed => $get('start_date'))
                            ->visible(fn (Get $get, string $operation): bool => static::shouldShowEndDate($get, $operation))
                            ->required()
                            ->columnSpan(['sm' => 2]),
                    ])
                        ->key('end_schedule')
                        ->label(__('End'))
                        ->extraAttributes(['class' => 'booking-end-schedule'])
                        ->columns(fn (Get $get, string $operation): array => [
                            'default' => 1,
                            'sm' => static::shouldShowEndDate($get, $operation) ? 3 : 1,
                        ])
                        ->columnSpan(fn (Get $get, string $operation): array => [
                            'default' => 1,
                            'sm' => static::shouldShowEndDate($get, $operation) ? 3 : 1,
                        ]),
                ]),
            TimezoneSelect::make('timezone')
                ->searchable()
                ->required()
                ->selectablePlaceholder(false)
                ->default(static::defaultTimezone())
                ->live()
                ->afterStateUpdated(function (mixed $state, mixed $old, Set $set, Get $get): void {
                    static::changeScheduleTimezone($state, $old, $set, $get);
                })
                ->columnSpanFull(),
            Select::make('venue_id')
                ->label(__('Venue'))
                ->options(fn (): array => static::venueOptions())
                ->default(fn (): ?string => static::teamDefault('venues'))
                ->selectablePlaceholder(false)
                ->searchable()
                ->live()
                ->required()
                ->afterStateUpdated(function (mixed $state): void {
                    $team = Filament::getTenant();

                    if (! $team instanceof Team || ! is_string($state) || $state === self::OTHER_VENUE) {
                        return;
                    }

                    $venue = Venue::query()->find($state);

                    if (
                        $venue instanceof Venue
                        && ! $team->venues()->whereKey($venue->getKey())->exists()
                    ) {
                        $team->addVenue($venue);
                    }
                })
                ->createOptionForm(fn (): array => VenueLocationSchema::creationSteps())
                ->createOptionAction(fn (Action $action): Action => $action
                    ->steps(fn (): array => VenueLocationSchema::creationSteps())
                    ->modifyWizardUsing(fn (Wizard $wizard): Wizard => VenueLocationSchema::prepareActionWizard($wizard))
                )
                ->createOptionUsing(function (array $data): string {
                    $team = Filament::getTenant();

                    if (! $team instanceof Team) {
                        throw ValidationException::withMessages([
                            'venue_id' => __('Choose a team.'),
                        ]);
                    }

                    $similarVenueId = $data['similar_venue_id'] ?? null;

                    if (! (is_string($similarVenueId) && $similarVenueId !== '')) {
                        VenueLocationSchema::validateCatalogName($data);
                    }

                    $venue = is_string($similarVenueId) && $similarVenueId !== ''
                        ? Venue::query()->findOrFail($similarVenueId)
                        : Venue::create(Arr::only($data, VenueLocationSchema::venueAttributes()));

                    $team->addVenue($venue);

                    return (string) $venue->getKey();
                }),
            ...VenueLocationSchema::components(
                visible: fn (Get $get): bool => $get('venue_id') === self::OTHER_VENUE,
            ),
            MarkdownEditor::make('notes')
                ->label(__('Team notes'))
                ->columnSpanFull(),
            MarkdownEditor::make('lead_instructor_notes')
                ->helperText('These notes will be visible to the Lead instructor. You can use these to share access arrangements, gate codes, etc.')
                ->visible(fn (Get $get): bool => (bool) ($get('requires_lead_instructor') ?? true))
                ->columnSpanFull(),
            Section::make(__('Settings'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->collapsed()
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('requires_lead_instructor')
                        ->label(__('Require lead instructor'))
                        ->helperText(__('Turn off for events led by someone outside the team. Record the organiser in the notes if needed.'))
                        ->default(true)
                        ->live(),
                ]),
        ];
    }

    public static function activityRequiresLabel(?string $activityId): bool
    {
        if ($activityId === null || $activityId === self::OTHER_ACTIVITY) {
            return false;
        }

        return (bool) Activity::query()
            ->whereKey($activityId)
            ->where('requires_activity_label', true)
            ->exists();
    }

    public static function teamOptions(string $relation): array
    {
        $team = Filament::getTenant();

        if (! $team instanceof Team) {
            return [];
        }

        return $team->{$relation}()
            ->orderBy('name')
            ->pluck('name', $relation === 'activities' ? 'activities.id' : 'venues.id')
            ->all();
    }

    public static function activityOptions(): array
    {
        return [
            ...static::teamOptions('activities'),
            self::OTHER_ACTIVITY => __('Other'),
        ];
    }

    public static function teamHasCatalogActivities(): bool
    {
        $team = Filament::getTenant();

        return $team instanceof Team && $team->activities()->exists();
    }

    public static function venueOptions(): array
    {
        $team = Filament::getTenant();

        if (! $team instanceof Team) {
            return [self::OTHER_VENUE => __('Other')];
        }

        $teamVenues = $team->venues()
            ->orderBy('venues.name')
            ->get(['venues.id', 'venues.name', 'venues.street', 'venues.city', 'venues.region', 'venues.postcode'])
            ->mapWithKeys(fn (Venue $venue): array => [
                (string) $venue->getKey() => $venue->optionLabel(),
            ])
            ->all();
        $otherVenues = Venue::selectOptions(
            Venue::query()->whereDoesntHave(
                'teams',
                fn (Builder $teams) => $teams->whereKey($team->getKey()),
            ),
        );

        $options = [];

        if ($teamVenues !== []) {
            $options[__('This team')] = $teamVenues;
        }

        if ($otherVenues !== []) {
            $options[__('Other teams')] = $otherVenues;
        }

        $options[self::OTHER_VENUE] = __('Other');

        return $options;
    }

    private static function teamDefault(string $relation): ?string
    {
        $team = Filament::getTenant();
        $defaultId = match ($relation) {
            'activities' => $team instanceof Team ? $team->default_activity_id : null,
            'venues' => $team instanceof Team ? $team->default_venue_id : null,
            default => null,
        };

        return $defaultId === null ? null : (string) $defaultId;
    }

    public static function audienceOptions(?string $activityId): array
    {
        if (blank($activityId)) {
            return [];
        }

        return Audience::query()
            ->whereHas(
                'activityAudiences',
                fn ($query) => $query->where('activity_id', $activityId),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function defaultAudienceId(?string $activityId): ?string
    {
        $team = Filament::getTenant();

        if (! $team instanceof Team || blank($activityId)) {
            return null;
        }

        $activity = Activity::query()->find($activityId);

        if (! $activity instanceof Activity) {
            return null;
        }

        $audienceId = $team->defaultAudienceFor($activity)?->getKey();

        return $audienceId === null ? null : (string) $audienceId;
    }

    public static function defaultTimezone(): string
    {
        $timezone = auth()->user()?->timezone;

        if ($timezone instanceof DateTimeZone) {
            return $timezone->getName();
        }

        return filled($timezone) ? (string) $timezone : 'Europe/London';
    }

    public static function hydrateBookingFormData(array $data, Booking $booking): array
    {
        $data = static::hydrateScheduleData($data);

        if (blank($data['activity_id'] ?? null)) {
            $data['activity_id'] = self::OTHER_ACTIVITY;
        }

        $data['show_activity_label'] = $data['activity_id'] !== self::OTHER_ACTIVITY
            && filled($data['activity_label'] ?? null);

        $privateVenue = Venue::onlyPrivate()
            ->where('booking_id', $booking->getKey())
            ->whereKey($booking->venue_id)
            ->first();

        if ($privateVenue instanceof Venue) {
            $data['venue_id'] = self::OTHER_VENUE;

            foreach (VenueLocationSchema::venueAttributes() as $attribute) {
                $data[$attribute] = $privateVenue->{$attribute};
            }

            $data['has_map_pin'] = $privateVenue->coords !== null;
        }

        return $data;
    }

    public static function scheduleTimezone(Get $get): string
    {
        $timezone = $get('timezone');

        return filled($timezone) ? (string) $timezone : static::defaultTimezone();
    }

    public static function shouldShowEndDate(Get $get, string $operation): bool
    {
        return $operation === 'edit'
            || (filled($get('end_date')) && $get('end_date') !== $get('start_date'));
    }

    public static function defaultScheduleStart(): Carbon
    {
        return Carbon::parse('next Saturday 09:30', static::defaultTimezone());
    }

    public static function defaultScheduleEnd(): Carbon
    {
        return static::defaultScheduleStart()->addHours(2);
    }

    public static function shiftEndToKeepDuration(mixed $state, mixed $old, bool $isDate, Set $set, Get $get): void
    {
        $timezone = static::scheduleTimezone($get);
        $startDate = $isDate ? $state : $get('start_date');
        $startTime = $isDate ? $get('start_time') : $state;
        $previousStartDate = $isDate ? $old : $get('start_date');
        $previousStartTime = $isDate ? $get('start_time') : $old;
        $start = static::makeScheduleDateTime($startDate, $startTime, $timezone);

        if ($start === null) {
            return;
        }

        $previousStart = static::makeScheduleDateTime($previousStartDate, $previousStartTime, $timezone);
        $end = static::makeScheduleDateTime(
            $get('end_date') ?: $get('start_date'),
            $get('end_time'),
            $timezone,
        );
        $duration = ($previousStart !== null && $end !== null)
            ? max(0, (int) $previousStart->diffInSeconds($end, absolute: false))
            : 7200;

        static::setEndSchedule($start->addSeconds($duration), $set);
    }

    public static function updateEndTime(mixed $state, mixed $old, Set $set, Get $get): void
    {
        $timezone = static::scheduleTimezone($get);
        $start = static::makeScheduleDateTime($get('start_date'), $get('start_time'), $timezone);
        $endDate = static::dateValue($get('end_date')) ?? static::dateValue($get('start_date'));
        $endTime = static::timeValue($state);
        $previousEndTime = static::timeValue($old);

        if ($start === null || $endDate === null || $endTime === null) {
            return;
        }

        $startDate = $start->toDateString();

        if ($endDate === $startDate && $endTime < $start->format('H:i')) {
            $endDate = $start->copy()->addDay()->toDateString();
        } elseif (
            $endDate === $start->copy()->addDay()->toDateString()
            && $previousEndTime !== null
            && $previousEndTime < $start->format('H:i')
            && $endTime >= $start->format('H:i')
        ) {
            $endDate = $startDate;
        }

        $end = static::makeScheduleDateTime($endDate, $endTime, $timezone);

        if ($end !== null) {
            static::setEndSchedule($end, $set);
        }
    }

    public static function updateEndDate(mixed $state, Set $set, Get $get): void
    {
        $start = static::makeScheduleDateTime(
            $get('start_date'),
            $get('start_time'),
            static::scheduleTimezone($get),
        );
        $endDate = static::dateValue($state);
        $endTime = static::timeValue($get('end_time'));

        if (
            $start !== null
            && $endDate === $start->toDateString()
            && $endTime !== null
            && $endTime < $start->format('H:i')
        ) {
            $set('end_time', $start->format('H:i'));
        }
    }

    public static function changeScheduleTimezone(mixed $state, mixed $old, Set $set, Get $get): void
    {
        if (! filled($state) || ! filled($old) || $state === $old) {
            return;
        }

        $start = static::makeScheduleDateTime($get('start_date'), $get('start_time'), (string) $old);
        $end = static::makeScheduleDateTime(
            $get('end_date') ?: $get('start_date'),
            $get('end_time'),
            (string) $old,
        );

        if ($start !== null) {
            $start = $start->setTimezone((string) $state);
            $set('start_date', $start->toDateString());
            $set('start_time', $start->format('H:i'));
        }

        if ($end !== null) {
            static::setEndSchedule($end->setTimezone((string) $state), $set);
        }
    }

    public static function hydrateScheduleData(array $data): array
    {
        $timezone = (string) ($data['timezone'] ?? config('app.timezone'));

        foreach (['start_at' => 'start', 'end_at' => 'end'] as $key => $prefix) {
            if (blank($data[$key] ?? null)) {
                continue;
            }

            $dateTime = Carbon::parse($data[$key])->setTimezone($timezone);
            $data["{$prefix}_date"] = $dateTime->toDateString();
            $data["{$prefix}_time"] = $dateTime->format('H:i');
        }

        unset($data['start_at'], $data['end_at']);

        return $data;
    }

    public static function mergeScheduleData(array $data): array
    {
        $timezone = (string) ($data['timezone'] ?? config('app.timezone'));
        $start = static::makeScheduleDateTime($data['start_date'] ?? null, $data['start_time'] ?? null, $timezone);
        $endDate = $data['end_date'] ?? $data['start_date'] ?? null;
        $end = static::makeScheduleDateTime($endDate, $data['end_time'] ?? null, $timezone);

        if ($start === null || $end === null) {
            throw ValidationException::withMessages([
                'start_date' => __('A valid start date and time are required.'),
                'end_time' => __('A valid end date and time are required.'),
            ]);
        }

        if ($end->lessThan($start)) {
            throw ValidationException::withMessages([
                'end_date' => __('The end date and time must not be before the start.'),
            ]);
        }

        $data['start_at'] = $start->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $data['end_at'] = $end->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        unset($data['start_date'], $data['start_time'], $data['end_date'], $data['end_time']);

        if (! static::teamHasCatalogActivities() || ($data['activity_id'] ?? null) === self::OTHER_ACTIVITY) {
            $data['activity_id'] = null;
            $data['audience_id'] = null;
        }

        $data['activity_label'] = filled($data['activity_label'] ?? null)
            ? trim((string) $data['activity_label'])
            : null;

        $isPrivateVenue = ($data['venue_id'] ?? null) === self::OTHER_VENUE;
        $data['private_venue'] = $isPrivateVenue
            ? Arr::only($data, VenueLocationSchema::venueAttributes())
            : null;

        if ($isPrivateVenue) {
            $data['venue_id'] = null;
        }

        foreach (VenueLocationSchema::venueAttributes() as $attribute) {
            unset($data[$attribute]);
        }

        unset(
            $data['show_activity_label'],
            $data['similar_venue_id'],
            $data['has_map_pin'],
            $data['map'],
        );

        return $data;
    }

    private static function makeScheduleDateTime(mixed $date, mixed $time, string $timezone): ?Carbon
    {
        $date = static::dateValue($date);
        $time = static::timeValue($time);

        if ($date === null || $time === null) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m-d H:i', "{$date} {$time}", $timezone) ?: null;
    }

    private static function dateValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value, config('app.timezone'))->toDateString();
    }

    private static function timeValue(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('H:i');
        }

        if (preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', (string) $value) === 1) {
            return substr((string) $value, 0, 5);
        }

        return Carbon::parse($value, config('app.timezone'))->format('H:i');
    }

    private static function setEndSchedule(Carbon $end, Set $set): void
    {
        $set('end_date', $end->toDateString());
        $set('end_time', $end->format('H:i'));
    }
}
