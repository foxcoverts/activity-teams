<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\MerchandiseOrders\Pages\ListMerchandiseOrders;
use App\Filament\Teams\Resources\MerchandiseOrders\Pages\ViewMerchandiseOrder;
use App\Filament\Teams\Resources\MerchandiseOrders\Schemas\MerchandiseOrderInfolist;
use App\Filament\Teams\Resources\MerchandiseOrders\Tables\MerchandiseOrdersTable;
use App\Models\MerchandiseOrder;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MerchandiseOrderResource extends Resource
{
    protected static ?string $model = MerchandiseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Merchandise;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $modelLabel = 'order';

    protected static ?string $pluralModelLabel = 'orders';

    protected static ?string $slug = 'orders';

    public static function getRecordTitle(?Model $record): string|Htmlable
    {
        if (! $record instanceof MerchandiseOrder) {
            return parent::getRecordTitle($record);
        }

        $when = $record->submitted_at?->timezone(config('app.timezone'))?->format('j M Y');

        return $when
            ? __('Order :date', ['date' => $when])
            : __('Order');
    }

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

    public static function infolist(Schema $schema): Schema
    {
        return MerchandiseOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchandiseOrdersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['user', 'lines'])
            ->latest('submitted_at');

        $tenant = Filament::getTenant();
        $user = auth()->user();

        if ($tenant instanceof Team) {
            $query->whereBelongsTo($tenant);

            if ($user instanceof User && ! $user->manages($tenant)) {
                $query->whereBelongsTo($user);
            }
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchandiseOrders::route('/'),
            'view' => ViewMerchandiseOrder::route('/{record}'),
        ];
    }
}
