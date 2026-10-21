<?php

namespace App\Filament\Manage\Resources\Bookings;

use App\Enums\BookingStatus;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Manage\Resources\Bookings\Pages\EditBooking;
use App\Filament\Manage\Resources\Bookings\Pages\ListBookings;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Manage\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Models\Booking;
use App\Models\Team;
use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Flex;
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

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 1;

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
     * @return array<int, DateTimePicker|Flex|Select|Textarea|TextInput>
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
            Flex::make([
                DateTimePicker::make('start_at')
                    ->timezone(fn (Get $get): string => static::scheduleTimezone($get))
                    ->seconds(false)
                    ->default(fn (): Carbon => Carbon::parse('next Saturday 09:30', static::defaultTimezone()))
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (mixed $state, mixed $old, Set $set, Get $get): void {
                        static::shiftEndToKeepDuration($state, $old, $set, $get);
                    })
                    ->required(),
                DateTimePicker::make('end_at')
                    ->timezone(fn (Get $get): string => static::scheduleTimezone($get))
                    ->seconds(false)
                    ->default(fn (): Carbon => Carbon::parse('Saturday 11:30', static::defaultTimezone()))
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                        static::keepEndAfterStart($state, $set, $get);
                    })
                    ->required(),
                TimezoneSelect::make('timezone')
                    ->searchable()
                    ->required()
                    ->selectablePlaceholder(false)
                    ->default(static::defaultTimezone())
                    ->live(),
            ])->from('md'),
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
                TextEntry::make('activity.name')
                    ->label('Activity'),
                TextEntry::make('venue.name')
                    ->label('Venue'),
                TextEntry::make('start_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('end_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('timezone')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('group_name'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('leadInstructor.name')
                    ->label('Lead instructor')
                    ->placeholder('-'),
                TextEntry::make('lead_instructor_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
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
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_at')
                    ->dateTime()
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
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
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

    public static function scheduleTimezone(Get $get): string
    {
        $timezone = $get('timezone');

        return filled($timezone) ? (string) $timezone : static::defaultTimezone();
    }

    public static function shiftEndToKeepDuration(mixed $state, mixed $old, Set $set, Get $get): void
    {
        $start = static::parseSchedule($state);

        if ($start === null) {
            return;
        }

        $previousStart = static::parseSchedule($old);
        $end = static::parseSchedule($get('end_at'));
        $duration = 120;

        if ($previousStart !== null && $end !== null) {
            $duration = (int) $previousStart->diffInMinutes($end, absolute: false);

            if ($duration < 0) {
                $duration = 0;
            }
        }

        $set('end_at', $start->addMinutes($duration)->format('Y-m-d H:i:s'));
    }

    public static function keepEndAfterStart(mixed $state, Set $set, Get $get): void
    {
        $end = static::parseSchedule($state);
        $start = static::parseSchedule($get('start_at'));

        if ($end === null || $start === null || $end->greaterThanOrEqualTo($start)) {
            return;
        }

        $set('end_at', $start->format('Y-m-d H:i:s'));
    }

    protected static function parseSchedule(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value)->timezone(config('app.timezone'));
        }

        return Carbon::parse($value, config('app.timezone'));
    }
}
