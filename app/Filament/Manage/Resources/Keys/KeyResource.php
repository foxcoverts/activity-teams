<?php

namespace App\Filament\Manage\Resources\Keys;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Keys\Pages\CreateKey;
use App\Filament\Manage\Resources\Keys\Pages\EditKey;
use App\Filament\Manage\Resources\Keys\Pages\ListKeys;
use App\Filament\Manage\Resources\Keys\Pages\ViewKey;
use App\Models\Key;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KeyResource extends Resource
{
    protected static ?string $model = Key::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('venue_id')
                    ->relationship('venue', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Select::make('holder_id')
                    ->relationship('holder', 'name'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('venue.name')
                    ->label('Venue'),
                TextEntry::make('name'),
                TextEntry::make('holder.name')
                    ->label('Holder')
                    ->placeholder('-'),
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
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('venue.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('holder.name')
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
            'index' => ListKeys::route('/'),
            'create' => CreateKey::route('/create'),
            'view' => ViewKey::route('/{record}'),
            'edit' => EditKey::route('/{record}/edit'),
        ];
    }
}
