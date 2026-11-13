<?php

namespace App\Filament\Teams\Resources\EquipmentSets;

use App\Enums\TeamFeature;
use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\EquipmentSets\Pages\CreateEquipmentSet;
use App\Filament\Teams\Resources\EquipmentSets\Pages\EditEquipmentSet;
use App\Filament\Teams\Resources\EquipmentSets\Pages\ListEquipmentSets;
use App\Filament\Teams\Resources\EquipmentSets\Schemas\EquipmentSetForm;
use App\Filament\Teams\Resources\EquipmentSets\Tables\EquipmentSetsTable;
use App\Models\EquipmentSet;
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

class EquipmentSetResource extends Resource
{
    protected static ?string $model = EquipmentSet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Setup;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Equipment sets';

    protected static ?string $modelLabel = 'equipment set';

    protected static ?string $pluralModelLabel = 'equipment sets';

    protected static ?string $slug = 'equipment-sets';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return EquipmentSetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentSetsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['activities']);
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereBelongsTo($team);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('viewAny', EquipmentSet::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny() && static::debriefEnabled();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('create', EquipmentSet::class);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $record instanceof EquipmentSet
            && $user instanceof User
            && $user->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return $record instanceof EquipmentSet
            && $user instanceof User
            && $user->can('delete', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipmentSets::route('/'),
            'create' => CreateEquipmentSet::route('/create'),
            'edit' => EditEquipmentSet::route('/{record}/edit'),
        ];
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }

    public static function debriefEnabled(): bool
    {
        $team = static::currentTeam();

        return $team instanceof Team && $team->featureEnabled(TeamFeature::Debrief);
    }
}
