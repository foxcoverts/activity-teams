<?php

namespace App\Filament\Teams\Resources\MerchandiseShops;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\MerchandiseShops\Pages\ListMerchandiseShops;
use App\Filament\Teams\Resources\MerchandiseShops\Pages\ViewMerchandiseShop;
use App\Filament\Teams\Resources\MerchandiseShops\Tables\MerchandiseShopsTable;
use App\Models\MerchandiseItem;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MerchandiseShopResource extends Resource
{
    protected static ?string $model = MerchandiseItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Merchandise;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Shop';

    protected static ?string $modelLabel = 'shop item';

    protected static ?string $pluralModelLabel = 'shop';

    protected static ?string $slug = 'shop';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canViewAny(): bool
    {
        if (! parent::canViewAny()) {
            return false;
        }

        $user = auth()->user();
        $team = Filament::getTenant();

        return $user instanceof User
            && $team instanceof Team
            && $user->isCurrentMemberOf($team);
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

    public static function table(Table $table): Table
    {
        return MerchandiseShopsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->available()
            ->with([
                'images',
                'variants' => fn ($variants) => $variants->available()->orderBy('sort')->orderBy('label'),
            ]);

        $tenant = Filament::getTenant();
        if ($tenant instanceof Team) {
            $query->whereBelongsTo($tenant);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchandiseShops::route('/'),
            'view' => ViewMerchandiseShop::route('/{record}'),
        ];
    }
}
