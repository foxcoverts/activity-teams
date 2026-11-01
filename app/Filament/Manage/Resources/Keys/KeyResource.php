<?php

namespace App\Filament\Manage\Resources\Keys;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Keys\Pages\ListKeys;
use App\Filament\Manage\Resources\Keys\Pages\ViewKey;
use App\Filament\Manage\Resources\Keys\Schemas\KeyInfolist;
use App\Filament\Manage\Resources\Keys\Tables\KeysTable;
use App\Models\Key;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KeyResource extends Resource
{
    protected static ?string $model = Key::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'key';

    protected static ?string $pluralModelLabel = 'keys';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        $team = static::currentTeam();

        return $user instanceof User
            && $team instanceof Team
            && ($user->manages($team) || $user->isSystemAdmin());
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

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
        $query = parent::getEloquentQuery()
            ->with([
                'holder',
                'locks.covers.requirements.activity',
                'locks.covers.requirements.venue',
                'locks.requirements.activity',
                'locks.requirements.venue',
            ]);
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        return $query->relevantTo($team);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KeyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KeysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKeys::route('/'),
            'view' => ViewKey::route('/{record}'),
        ];
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
