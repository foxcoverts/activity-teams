<?php

namespace App\Filament\Teams\Resources\BookingDebriefs;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\BookingDebriefs\Pages\ListBookingDebriefs;
use App\Filament\Teams\Resources\BookingDebriefs\Pages\ViewBookingDebrief;
use App\Filament\Teams\Resources\BookingDebriefs\Schemas\BookingDebriefInfolist;
use App\Filament\Teams\Resources\BookingDebriefs\Tables\BookingDebriefsTable;
use App\Models\BookingDebrief;
use App\Models\Team;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookingDebriefResource extends Resource
{
    protected static ?string $model = BookingDebrief::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?string $navigationParentItem = 'Bookings';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Debriefs';

    protected static ?string $modelLabel = 'debrief';

    protected static ?string $pluralModelLabel = 'debriefs';

    protected static ?string $slug = 'debriefs';

    protected static bool $isScopedToTenant = false;

    public static function getRecordTitle(?Model $record): ?string
    {
        if (! $record instanceof BookingDebrief) {
            return null;
        }

        $record->loadMissing('booking.activity');

        return $record->booking?->activityTitle() ?? __('Debrief');
    }

    public static function infolist(Schema $schema): Schema
    {
        return BookingDebriefInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookingDebriefsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'booking.activity',
                'booking.audience',
                'booking.venue',
                'booking.team',
                'completedBy',
                'equipmentSets',
            ]);

        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
            'booking',
            fn (Builder $bookings): Builder => $bookings->where('team_id', $team->getKey()),
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookingDebriefs::route('/'),
            'view' => ViewBookingDebrief::route('/{record}'),
        ];
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
