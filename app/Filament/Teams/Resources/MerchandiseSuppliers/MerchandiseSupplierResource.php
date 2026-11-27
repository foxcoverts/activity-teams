<?php

namespace App\Filament\Teams\Resources\MerchandiseSuppliers;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\MerchandiseSuppliers\Pages\CreateMerchandiseSupplier;
use App\Filament\Teams\Resources\MerchandiseSuppliers\Pages\EditMerchandiseSupplier;
use App\Filament\Teams\Resources\MerchandiseSuppliers\Pages\ListMerchandiseSuppliers;
use App\Filament\Teams\Resources\MerchandiseSuppliers\Pages\ViewMerchandiseSupplier;
use App\Filament\Teams\Resources\MerchandiseSuppliers\Schemas\MerchandiseSupplierForm;
use App\Filament\Teams\Resources\MerchandiseSuppliers\Schemas\MerchandiseSupplierInfolist;
use App\Filament\Teams\Resources\MerchandiseSuppliers\Tables\MerchandiseSuppliersTable;
use App\Models\MerchandiseSupplier;
use App\Models\Team;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MerchandiseSupplierResource extends Resource
{
    protected static ?string $model = MerchandiseSupplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Merchandise;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Suppliers';

    protected static ?string $modelLabel = 'supplier';

    protected static ?string $pluralModelLabel = 'suppliers';

    protected static ?string $slug = 'merchandise-suppliers';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MerchandiseSupplierForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MerchandiseSupplierInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchandiseSuppliersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withCount('items');

        $tenant = Filament::getTenant();
        if ($tenant instanceof Team) {
            $query->whereBelongsTo($tenant);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchandiseSuppliers::route('/'),
            'create' => CreateMerchandiseSupplier::route('/create'),
            'view' => ViewMerchandiseSupplier::route('/{record}'),
            'edit' => EditMerchandiseSupplier::route('/{record}/edit'),
        ];
    }
}
