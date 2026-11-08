<?php

namespace App\Filament\Admin\Resources\Venues;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Venues\Pages\CreateVenue;
use App\Filament\Admin\Resources\Venues\Pages\EditVenue;
use App\Filament\Admin\Resources\Venues\Pages\ListVenues;
use App\Filament\Admin\Resources\Venues\Pages\ViewVenue;
use App\Filament\Venues\VenueLocationSchema;
use App\Models\User;
use App\Models\Venue;
use BackedEnum;
use Darkclow4\FilamentMapPicker\Infolists\Components\MapEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VenueResource extends Resource
{
    protected static ?string $model = Venue::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Catalog;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(VenueLocationSchema::components(
                includeNotes: true,
            ));
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
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('address')
                    ->state(fn (Venue $record): array => $record->location->addressArray())
                    ->searchable(),
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
