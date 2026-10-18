<?php

namespace App\Filament\Team\Resources\Bookings;

use App\Enums\BookingStatus;
use App\Filament\Team\Resources\Bookings\Pages\ListBookings;
use App\Filament\Team\Resources\Bookings\Pages\ViewBooking;
use App\Models\Booking;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
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

    protected static ?string $recordTitleAttribute = 'group_name';

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
            ->with(['activity', 'venue', 'attendees'])
            ->forUser(auth()->user())
            ->ordered();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('activity.name')->label(__('Activity')),
                TextEntry::make('venue.name')->label(__('Venue')),
                TextEntry::make('start_at')->dateTime()->placeholder('-'),
                TextEntry::make('end_at')->dateTime()->placeholder('-'),
                TextEntry::make('status')
                    ->badge()
                    ->icon(fn (BookingStatus $state): Heroicon => $state->getIcon()),
                TextEntry::make('group_name'),
                TextEntry::make('notes')->placeholder('-')->columnSpanFull(),
                TextEntry::make('leadInstructor.name')->label(__('Lead instructor'))->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('group_name')
            ->columns([
                TextColumn::make('start_at')->dateTime()->sortable(),
                TextColumn::make('activity.name')->searchable(),
                TextColumn::make('venue.name')->searchable(),
                TextColumn::make('group_name')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->icon(fn (BookingStatus $state): Heroicon => $state->getIcon()),
            ])
            ->filters([
                SelectFilter::make('status')->options(BookingStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('start_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'view' => ViewBooking::route('/{record}'),
        ];
    }
}
