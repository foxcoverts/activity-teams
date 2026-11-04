<?php

namespace App\Filament\Teams\Resources\Bookings;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Filament\Bookings\LocationMapAction;
use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\Bookings\Pages\ListBookings;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingWhen;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->with(['activity', 'audience', 'team', 'venue', 'attendees'])
            ->when(
                $user instanceof User,
                fn (Builder $query): Builder => $query->forUser($user),
                fn (Builder $query): Builder => $query->whereRaw('0 = 1'),
            )
            ->ordered();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components(static::infolistComponents());
    }

    /**
     * @return array<int, Section>
     */
    public static function infolistComponents(): array
    {
        return [
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
                    TextEntry::make('activity_title')
                        ->label(__('Activity'))
                        ->state(fn (Booking $record): string => $record->activityTitle()),
                    TextEntry::make('audience.name')
                        ->label(__('Audience'))
                        ->placeholder('-'),
                    TextEntry::make('group_name')
                        ->label(__('Group'))
                        ->placeholder('-')
                        ->columnSpanFull(),
                    TextEntry::make('notes')
                        ->label(__('Notes'))
                        ->visible(fn (Booking $record): bool => filled($record->notes))
                        ->columnSpanFull(),
                    TextEntry::make('lead_instructor_notes')
                        ->label(__('Lead instructor notes'))
                        ->visible(function (Booking $record): bool {
                            $user = auth()->user();
                            $record->loadMissing('team');

                            return filled($record->lead_instructor_notes)
                                && $user instanceof User
                                && $record->team !== null
                                && ($record->isLead($user) || $user->manages($record->team));
                        })
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->columns([
                TextColumn::make('start_at')->dateTime('M j, Y H:i')->sortable(),
                TextColumn::make('activity.name')
                    ->state(fn (Booking $record): string => $record->activityTitle())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(function (Builder $query) use ($search): void {
                            $query
                                ->where('activity_label', 'like', "%{$search}%")
                                ->orWhereHas('activity', fn (Builder $activityQuery) => $activityQuery
                                    ->where('name', 'like', "%{$search}%"));
                        })),
                TextColumn::make('venue.name')->searchable(),
                TextColumn::make('status')
                    ->label(__('Booking Status'))
                    ->badge()
                    ->icon(fn (BookingStatus $state): Heroicon => $state->getIcon()),
                TextColumn::make('attendance')
                    ->state(fn (Booking $record): ?BookingAttendeeStatus => $record->attendees
                        ->firstWhere('id', auth()->id())
                        ?->attendance
                        ?->status)
                    ->badge()
                    ->label(__('Attendance')),
            ])
            ->filters([
                SelectFilter::make('status')->options(BookingStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('start_at');
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
            'view' => ViewBooking::route('/{record}'),
        ];
    }
}
