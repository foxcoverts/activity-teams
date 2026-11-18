<?php

namespace App\Filament\Teams\Resources\EquipmentSets;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\EquipmentSets\Pages\ManageEquipmentSets;
use App\Filament\Teams\Resources\EquipmentSets\Schemas\EquipmentSetForm;
use App\Models\EquipmentSet;
use App\Models\EquipmentSetGroup;
use App\Models\Team;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class EquipmentSetResource extends Resource
{
    protected static ?string $model = EquipmentSet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Setup;

    protected static ?int $navigationSort = 2;

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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['activities', 'equipmentSetGroup']);
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        EquipmentSetGroup::ensureDefaultFor($team);

        return $query->whereBelongsTo($team);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEquipmentSets::route('/'),
        ];
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
