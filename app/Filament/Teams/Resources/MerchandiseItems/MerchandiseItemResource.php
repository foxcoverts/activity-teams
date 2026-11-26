<?php

namespace App\Filament\Teams\Resources\MerchandiseItems;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\CreateMerchandiseItem;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\EditMerchandiseItem;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\ListMerchandiseItems;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\ViewMerchandiseItem;
use App\Filament\Teams\Resources\MerchandiseItems\Schemas\MerchandiseItemForm;
use App\Filament\Teams\Resources\MerchandiseItems\Schemas\MerchandiseItemInfolist;
use App\Filament\Teams\Resources\MerchandiseItems\Tables\MerchandiseItemsTable;
use App\Models\MerchandiseItem;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MerchandiseItemResource extends Resource
{
    protected static ?string $model = MerchandiseItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Merchandise;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Items';

    protected static ?string $modelLabel = 'item';

    protected static ?string $pluralModelLabel = 'items';

    protected static ?string $slug = 'merchandise-items';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canViewAny(): bool
    {
        if (! parent::canViewAny()) {
            return false;
        }

        $user = auth()->user();

        return $user instanceof User && $user->can('create', MerchandiseItem::class);
    }

    public static function form(Schema $schema): Schema
    {
        return MerchandiseItemForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MerchandiseItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchandiseItemsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['variants.optionValues', 'optionAxes.values', 'images']);

        $tenant = Filament::getTenant();
        if ($tenant instanceof Team) {
            $query->whereBelongsTo($tenant);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchandiseItems::route('/'),
            'create' => CreateMerchandiseItem::route('/create'),
            'view' => ViewMerchandiseItem::route('/{record}'),
            'edit' => EditMerchandiseItem::route('/{record}/edit'),
        ];
    }
}
