<?php

namespace App\Filament\Admin\Resources\Keys;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Keys\Pages\CreateKey;
use App\Filament\Admin\Resources\Keys\Pages\EditKey;
use App\Filament\Admin\Resources\Keys\Pages\ListKeys;
use App\Filament\Admin\Resources\Keys\Pages\ViewKey;
use App\Filament\Admin\Resources\Keys\RelationManagers\LocksRelationManager;
use App\Filament\Admin\Resources\Keys\Schemas\KeyForm;
use App\Filament\Admin\Resources\Keys\Schemas\KeyInfolist;
use App\Filament\Admin\Resources\Keys\Tables\KeysTable;
use App\Models\Key;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KeyResource extends Resource
{
    protected static ?string $model = Key::class;

    protected static ?string $modelLabel = 'key';

    protected static ?string $pluralModelLabel = 'keys';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Catalog;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return KeyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KeyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KeysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LocksRelationManager::class,
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
