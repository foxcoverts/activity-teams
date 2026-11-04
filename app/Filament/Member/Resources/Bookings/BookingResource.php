<?php

namespace App\Filament\Member\Resources\Bookings;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Filament\Member\NavigationGroup;
use App\Filament\Member\Resources\Bookings\Pages\ListBookings;
use App\Filament\Teams\Resources\Bookings\BookingResource as TeamsBookingResource;
use App\Models\Booking;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
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
        return parent::getEloquentQuery()
            ->with(['activity', 'audience', 'team', 'venue', 'attendees'])
            ->forUser(auth()->user())
            ->ordered();
    }

    public static function viewUrl(Booking $booking): string
    {
        return TeamsBookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $booking->team);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->recordUrl(fn (Booking $record): string => static::viewUrl($record))
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
                TextColumn::make('team.name')
                    ->visible(fn (HasTable $livewire): bool => blank($livewire->getTableFilterState('team_id')['value'] ?? null)),
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
                SelectFilter::make('team_id')
                    ->label(__('Team'))
                    ->options(fn (): array => auth()->user()?->teams()->orderBy('teams.name')->pluck('teams.name', 'teams.id')->all() ?? []),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Booking $record): string => static::viewUrl($record)),
            ])
            ->defaultSort('start_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
        ];
    }
}
