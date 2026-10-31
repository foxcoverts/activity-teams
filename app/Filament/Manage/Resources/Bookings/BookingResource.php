<?php

namespace App\Filament\Manage\Resources\Bookings;

use App\Enums\BookingStatus;
use App\Filament\Bookings\LocationMapAction;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Manage\Resources\Bookings\Pages\EditBooking;
use App\Filament\Manage\Resources\Bookings\Pages\ListBookings;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Manage\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Support\BookingWhen;
use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?string $recordTitleAttribute = 'summary';

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if ($record instanceof Booking && $record->isCancelled()) {
            return Response::deny(__('A cancelled booking cannot be edited. Restore it to make changes.'));
        }

        return parent::getEditAuthorizationResponse($record);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'attendees',
            'leadInstructor',
        ]);
    }

    /**
     * @return array<int, DatePicker|FusedGroup|Grid|Select|Textarea|TimePicker|TextInput>
     */
    public static function detailComponents(): array
    {
        return [
            Select::make('activity_id')
                ->label(__('Activity'))
                ->options(fn (): array => static::teamOptions('activities'))
                ->default(fn (): ?string => static::teamDefault('activities'))
                ->selectablePlaceholder(false)
                ->required(),
            TextInput::make('group_name')
                ->required()
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
                ->options(fn (): array => static::teamOptions('venues'))
                ->default(fn (): ?string => static::teamDefault('venues'))
                ->selectablePlaceholder(false)
                ->required(),
            Textarea::make('notes')
                ->label(__('Team notes'))
                ->columnSpanFull(),
            Textarea::make('lead_instructor_notes')
                ->helperText('These notes will be visible to the Lead instructor. You can use these to share access arrangements, gate codes, etc.')
                ->columnSpanFull(),
        ];
    }

    public static function form(Schema $schema): Schema
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn (?Booking $record): string => __(':Status booking', [
                    'status' => $record?->status->getLabel() ?? __('Booking'),
                ]))
                    ->icon(fn (?Booking $record): Heroicon => ($record?->status ?? BookingStatus::Tentative)->getIcon())
                    ->iconColor(fn (?Booking $record): array => ($record?->status ?? BookingStatus::Tentative)->getColor())
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('when')
                            ->label(__('When'))
                            ->state(fn (Booking $record): string => BookingWhen::format($record)),
                        TextEntry::make('location')
                            ->label(__('Location'))
                            ->state(fn (Booking $record): array => array_filter([
                                $record->location->name,
                                ...$record->location->addressArray(),
                            ]))
                            ->listWithLineBreaks()->limitList(1)->expandableLimitedList()
                            ->afterLabel([
                                LocationMapAction::make(),
                            ]),
                        TextEntry::make('activity.name')->label(__('Activity')),
                        TextEntry::make('group_name')
                            ->label(__('Group'))
                            ->columnSpanFull(),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('lead_instructor_notes')
                            ->label(__('Lead instructor notes'))
                            ->placeholder('-')
                            ->visible(function (Booking $record): bool {
                                $user = auth()->user();
                                $record->loadMissing('team');

                                return $user instanceof User
                                    && $record->team !== null
                                    && ($record->isLead($user) || $user->manages($record->team));
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('group_name')
            ->columns([
                TextColumn::make('activity.name')
                    ->searchable(),
                TextColumn::make('venue.name')
                    ->searchable(),
                TextColumn::make('start_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('end_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->icon(fn (BookingStatus $state): Heroicon => $state->getIcon())
                    ->searchable(),
                TextColumn::make('group_name')
                    ->searchable(),
                TextColumn::make('leadInstructor.name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    static::editAction(),
                    ...BookingStatusActions::actions(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AttendeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'view' => ViewBooking::route('/{record}'),
            'edit' => EditBooking::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
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

    public static function defaultTimezone(): string
    {
        $timezone = auth()->user()?->timezone;

        if ($timezone instanceof DateTimeZone) {
            return $timezone->getName();
        }

        return filled($timezone) ? (string) $timezone : 'Europe/London';
    }

    public static function createAction(): CreateAction
    {
        return CreateAction::make()
            ->mutateDataUsing(fn (array $data): array => static::mergeScheduleData($data));
    }

    public static function editAction(): EditAction
    {
        return EditAction::make()
            ->mutateRecordDataUsing(fn (array $data): array => static::hydrateScheduleData($data))
            ->mutateDataUsing(fn (array $data): array => static::mergeScheduleData($data));
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
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
