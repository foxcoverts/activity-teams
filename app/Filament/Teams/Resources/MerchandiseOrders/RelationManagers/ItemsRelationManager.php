<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\RelationManagers;

use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use App\Models\MerchandiseOrderLine;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Items';

    protected static ?string $modelLabel = 'item';

    protected static ?string $pluralModelLabel = 'items';

    protected static bool $shouldSkipAuthorization = true;

    protected static ?bool $isLazyLoaded = false;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('item.images')->orderBy('sort'))
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->square()
                    ->imageSize(48)
                    ->grow(false)
                    ->getStateUsing(fn (MerchandiseOrderLine $record): ?string => $record->item?->thumbnailImage()?->thumbnailUrl())
                    ->alt(fn (MerchandiseOrderLine $record): string => $record->item_title)
                    ->url(fn (MerchandiseOrderLine $record): ?string => static::productUrl($record)),
                TextColumn::make('item_title')
                    ->label(__('Item'))
                    ->url(fn (MerchandiseOrderLine $record): ?string => static::productUrl($record))
                    ->description(function (MerchandiseOrderLine $record): ?string {
                        $parts = collect($record->personalisationSnapshot())
                            ->map(fn (array $row): string => $row['label'].': '.$row['value']);

                        return $parts->isEmpty() ? null : $parts->implode(' · ');
                    }),
                TextColumn::make('variant_label')
                    ->label(__('Variant'))
                    ->placeholder('—'),
                TextColumn::make('unit_price')
                    ->label(__('Price'))
                    ->money('GBP')
                    ->alignEnd(),
                TextColumn::make('quantity')
                    ->label(__('Qty'))
                    ->alignEnd(),
                TextColumn::make('line_total')
                    ->label(__('Total'))
                    ->state(fn (MerchandiseOrderLine $record): float => $record->lineTotal())
                    ->money('GBP')
                    ->alignEnd()
                    ->summarize(
                        Summarizer::make('total')
                            ->label(__('Total'))
                            ->using(fn (Builder $query): float => (float) $query
                                ->sum(DB::raw('unit_price * quantity')))
                            ->money('GBP', locale: 'en_GB'),
                    ),
            ])
            ->paginated(false)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading(__('No items on this order.'));
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->lines()->count();

        return $count > 0 ? (string) $count : null;
    }

    private static function productUrl(MerchandiseOrderLine $record): ?string
    {
        if ($record->item === null) {
            return null;
        }

        return MerchandiseShopResource::getUrl('view', ['record' => $record->item]);
    }
}
