<?php

namespace App\Filament\Admin\Resources\Venues;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Venues\Pages\CreateVenue;
use App\Filament\Admin\Resources\Venues\Pages\EditVenue;
use App\Filament\Admin\Resources\Venues\Pages\ListVenues;
use App\Filament\Admin\Resources\Venues\Pages\ViewVenue;
use App\Models\User;
use App\Models\Venue;
use App\Support\Coords;
use BackedEnum;
use Darkclow4\FilamentMapPicker\Forms\Components\MapPicker;
use Darkclow4\FilamentMapPicker\Infolists\Components\MapEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Symfony\Component\Intl\Countries;

class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Venue Details'))
                    ->icon(Heroicon::BuildingOffice)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        FusedGroup::make([
                            TextInput::make('street'),
                            TextInput::make('street2'),
                        ])
                            ->label(__('Street')),
                        TextInput::make('city')
                            ->label(__('City')),
                        TextInput::make('region')
                            ->label(__('County')),
                        TextInput::make('postcode')
                            ->label(__('Postcode')),
                        Select::make('country')
                            ->label(__('Country'))
                            ->default('GB')
                            ->searchable()
                            ->reactive()
                            ->options(fn (): array => Countries::getNames()),
                        TextInput::make('slug')
                            ->label(__('Slug'))
                            ->prefix('/')
                            ->helperText(__('Leave blank to generate from the name.'))
                            ->alphaDash()
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Location'))
                    ->icon(Heroicon::Map)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Hidden::make('latitude')
                            ->requiredWith('longitude'),
                        Hidden::make('longitude')
                            ->requiredWith('latitude'),
                        Toggle::make('has_map_pin')
                            ->label(__('Map location'))
                            ->helperText(__('Turn on to place a pin. Turn off to remove the stored coordinates.'))
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Toggle $component, Get $get): void {
                                $component->state(
                                    is_numeric($get('latitude')) && is_numeric($get('longitude')),
                                );
                            })
                            ->afterStateUpdated(function (?bool $state, Set $set, Get $get): void {
                                if (! $state) {
                                    $set('latitude', null);
                                    $set('longitude', null);
                                    $set('map', null);

                                    return;
                                }

                                if (is_numeric($get('latitude')) && is_numeric($get('longitude'))) {
                                    return;
                                }

                                $set('latitude', Coords::DEFAULT_MAP_LATITUDE);
                                $set('longitude', Coords::DEFAULT_MAP_LONGITUDE);
                            }),
                        MapPicker::make('map')
                            ->hiddenLabel()
                            ->mode('click')
                            ->searchable()
                            ->defaultLocation(Coords::DEFAULT_MAP_LATITUDE, Coords::DEFAULT_MAP_LONGITUDE)
                            ->dehydrated(false)
                            ->visible(fn (Get $get): bool => (bool) $get('has_map_pin'))
                            ->zoom(13)
                            ->height(420)
                            ->live()
                            ->helperText(__('Click the map to move the pin.'))
                            ->afterStateHydrated(function (MapPicker $component, Get $get): void {
                                $latitude = $get('latitude');
                                $longitude = $get('longitude');

                                if (is_numeric($latitude) && is_numeric($longitude)) {
                                    $component->state([
                                        'lat' => (float) $latitude,
                                        'lng' => (float) $longitude,
                                    ]);
                                }
                            })
                            ->afterStateUpdated(function (?array $state, Set $set): void {
                                $set('latitude', is_numeric($state['lat'] ?? null) ? $state['lat'] : null);
                                $set('longitude', is_numeric($state['lng'] ?? null) ? $state['lng'] : null);
                            }),
                    ]),
            ]);
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
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
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
                ViewAction::make(),
                EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenues::route('/'),
            'create' => CreateVenue::route('/create'),
            'view' => ViewVenue::route('/{record}'),
            'edit' => EditVenue::route('/{record}/edit'),
        ];
    }
}
