<?php

namespace App\Filament\Teams\Resources\Bookings;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Teams\Resources\Bookings\Pages\EditBooking;
use App\Filament\Teams\Resources\Bookings\Pages\ListBookings;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Filament\Teams\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Teams\Resources\Bookings\Schemas\BookingInfolist;
use App\Filament\Teams\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if ($record instanceof Booking && $record->isCancelled()) {
            return Response::deny(__('A cancelled booking cannot be edited. Restore it to make changes.'));
        }

        return parent::getEditAuthorizationResponse($record);
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

    public static function form(Schema $schema): Schema
    {
        return BookingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookingsTable::configure($table);
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
}
