<?php

namespace App\Filament\Teams\Resources\MerchandiseSupplierOrders;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\MerchandiseSupplierOrders\Pages\ManageMerchandiseSupplierOrders;
use App\Filament\Teams\Resources\MerchandiseSupplierOrders\Pages\ViewMerchandiseSupplierOrder;
use App\Filament\Teams\Resources\MerchandiseSupplierOrders\Schemas\MerchandiseSupplierOrderInfolist;
use App\Filament\Teams\Resources\MerchandiseSupplierOrders\Tables\MerchandiseSupplierOrdersTable;
use App\Models\MerchandiseSupplierOrder;
use App\Models\Team;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MerchandiseSupplierOrderResource extends Resource
{
    protected static ?string $model = MerchandiseSupplierOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Merchandise;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Supplier orders';

    protected static ?string $modelLabel = 'supplier order';

    protected static ?string $pluralModelLabel = 'supplier orders';

    protected static ?string $slug = 'merchandise-supplier-orders';

    public static function getRecordTitle(?Model $record): string|Htmlable
    {
        if (! $record instanceof MerchandiseSupplierOrder) {
            return parent::getRecordTitle($record);
        }

        $record->loadMissing('supplier');

        $supplierName = $record->supplier?->name ?? __('Supplier');

        if ($record->isDraft()) {
            return __('Draft — :supplier', ['supplier' => $supplierName]);
        }

        $when = $record->sent_at?->timezone(config('app.timezone'))?->format('j M Y');

        return $when
            ? __(':supplier — :date', ['supplier' => $supplierName, 'date' => $when])
            : $supplierName;
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
        return MerchandiseSupplierOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchandiseSupplierOrdersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['supplier', 'lines'])
            ->withCount('lines')
            ->latest('sent_at')
            ->latest('updated_at');

        $tenant = Filament::getTenant();
        if ($tenant instanceof Team) {
            $query->whereBelongsTo($tenant);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMerchandiseSupplierOrders::route('/'),
            'view' => ViewMerchandiseSupplierOrder::route('/{record}'),
        ];
    }
}
