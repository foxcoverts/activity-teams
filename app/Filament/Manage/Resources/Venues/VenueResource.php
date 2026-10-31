<?php

namespace App\Filament\Manage\Resources\Venues;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Venues\Pages\ListVenues;
use App\Filament\Manage\Resources\Venues\Pages\ViewVenue;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Support\Palette;
use BackedEnum;
use Darkclow4\FilamentMapPicker\Infolists\Components\MapEntry;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?string $navigationLabel = 'Venues';

    protected static ?string $modelLabel = 'venue';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        $team = static::currentTeam();

        return $user instanceof User
            && $team instanceof Team
            && ($user->manages($team) || $user->isSystemAdmin());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
            'teams',
            fn (Builder $teams) => $teams->whereKey($team->getKey()),
        );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Venue Details'))
                    ->icon(Heroicon::BuildingOffice)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('address')
                            ->label(__('Address'))
                            ->state(fn (Venue $record): array => $record->location->addressArray())
                            ->listWithLineBreaks()
                            ->placeholder('-'),
                        TextEntry::make('notes')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Location'))
                    ->icon(Heroicon::Map)
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'venue-map-panel'])
                    ->schema([
                        MapEntry::make('location')
                            ->hiddenLabel()
                            ->state(fn (Venue $record): array => [
                                'lat' => (float) $record->latitude,
                                'lng' => (float) $record->longitude,
                            ])
                            ->interactive()
                            ->height(360)
                            ->zoom(15),
                    ])
                    ->visible(fn (Venue $record): bool => is_numeric($record->latitude) && is_numeric($record->longitude)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('default_venue')
                    ->label(__('Default'))
                    ->state(fn (Venue $record): string => static::currentTeam()?->default_venue_id === $record->getKey()
                        ? __('Default')
                        : __('Set as default'))
                    ->badge(fn (Venue $record): bool => static::currentTeam()?->default_venue_id === $record->getKey())
                    ->color(fn (Venue $record): string|array => static::currentTeam()?->default_venue_id === $record->getKey()
                        ? Palette::positive()
                        : 'primary')
                    ->extraAttributes(fn (Venue $record): array => static::currentTeam()?->default_venue_id === $record->getKey()
                        ? []
                        : ['class' => 'team-default-action'])
                    ->disabledClick(fn (Venue $record): bool => static::currentTeam()?->default_venue_id === $record->getKey())
                    ->action(
                        Action::make('setDefault')
                            ->label('Set as default')
                            ->color('primary')
                            ->authorize(fn (): bool => static::canViewAny())
                            ->visible(fn (Venue $record): bool => static::currentTeam()?->default_venue_id !== $record->getKey())
                            ->action(function (Venue $record): void {
                                $team = static::currentTeam();
                                abort_unless($team instanceof Team, 404);

                                $team->setDefaultVenue($record);
                            }),
                    ),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Remove from team')
                    ->color(Palette::destructive())
                    ->requiresConfirmation()
                    ->action(function (Venue $record): void {
                        static::currentTeam()?->removeVenue($record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenues::route('/'),
            'view' => ViewVenue::route('/{record}'),
        ];
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
