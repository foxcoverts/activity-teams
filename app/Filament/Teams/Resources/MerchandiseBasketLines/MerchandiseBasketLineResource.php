<?php

namespace App\Filament\Teams\Resources\MerchandiseBasketLines;

use App\Enums\TeamFeature;
use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\MerchandiseBasketLines\Pages\ListMerchandiseBasketLines;
use App\Filament\Teams\Resources\MerchandiseBasketLines\Tables\MerchandiseBasketLinesTable;
use App\Models\MerchandiseBasketLine;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MerchandiseBasketLineResource extends Resource
{
    protected static ?string $model = MerchandiseBasketLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Merchandise;

    protected static ?string $navigationParentItem = 'Shop';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Basket';

    protected static ?string $modelLabel = 'basket line';

    protected static ?string $pluralModelLabel = 'basket';

    protected static ?string $slug = 'basket';

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

    public static function table(Table $table): Table
    {
        return MerchandiseBasketLinesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['item.images', 'variant']);

        $tenant = Filament::getTenant();
        $user = auth()->user();

        if ($tenant instanceof Team) {
            $query->whereBelongsTo($tenant);
        }

        if ($user instanceof User) {
            $query->whereBelongsTo($user);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchandiseBasketLines::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::quantityInBasket();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Total quantity of lines in the current member’s basket for the tenant team.
     */
    public static function quantityInBasket(?Team $team = null, ?User $user = null): int
    {
        $team ??= Filament::getTenant();
        $user ??= auth()->user();

        if (! $team instanceof Team || ! $user instanceof User) {
            return 0;
        }

        if (! $team->featureEnabled(TeamFeature::Shop) || ! $user->isCurrentMemberOf($team)) {
            return 0;
        }

        return (int) MerchandiseBasketLine::query()
            ->forTeamUser($team, $user)
            ->sum('quantity');
    }
}
