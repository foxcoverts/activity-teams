<?php

namespace App\Filament\Teams\Resources\MerchandiseBasketLines\Tables;

use App\Filament\Support\RefreshesNavigationChrome;
use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use App\Models\MerchandiseBasketLine;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MerchandiseBasketLinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->square()
                    ->imageSize(48)
                    ->grow(false)
                    ->getStateUsing(fn (MerchandiseBasketLine $record): ?string => $record->item?->thumbnailImage()?->thumbnailUrl())
                    ->alt(fn (MerchandiseBasketLine $record): ?string => $record->item?->title)
                    ->url(fn (MerchandiseBasketLine $record): ?string => static::productUrl($record)),
                TextColumn::make('item.title')
                    ->label(__('Item'))
                    ->url(fn (MerchandiseBasketLine $record): ?string => static::productUrl($record))
                    ->description(function (MerchandiseBasketLine $record): ?string {
                        $parts = collect($record->personalisationSnapshot())
                            ->map(fn (array $row): string => $row['label'].': '.$row['value']);

                        return $parts->isEmpty() ? null : $parts->implode(' · ');
                    }),
                TextColumn::make('variant.label')
                    ->label(__('Variant')),
                TextColumn::make('variant.price')
                    ->label(__('Price'))
                    ->money('GBP')
                    ->alignEnd(),
                TextInputColumn::make('quantity')
                    ->label(__('Qty'))
                    ->type('number')
                    ->rules(['required', 'integer', 'min:0'])
                    ->grow(false)
                    ->extraAttributes(['class' => 'basket-qty-input'])
                    ->updateStateUsing(function (MerchandiseBasketLine $record, mixed $state): mixed {
                        $quantity = (int) $state;

                        if ($quantity < 1) {
                            $record->delete();

                            return 0;
                        }

                        $record->update(['quantity' => $quantity]);

                        return $quantity;
                    })
                    ->afterStateUpdated(function (?Component $livewire, mixed $state): void {
                        RefreshesNavigationChrome::dispatch($livewire);

                        // Only rebuild when the line is gone — a full reset steals focus from qty.
                        if ((int) $state >= 1 || $livewire === null || ! method_exists($livewire, 'resetTable')) {
                            return;
                        }

                        $livewire->resetTable();
                    }),
                IconColumn::make('remove')
                    ->label('')
                    ->state(true)
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->tooltip(__('Remove'))
                    ->alignCenter()
                    ->grow(false)
                    ->width('1%')
                    ->action(
                        DeleteAction::make('remove')
                            ->label(__('Remove'))
                            ->modalHeading(__('Remove item'))
                            ->modalDescription(__('Remove this item from your basket?'))
                            ->modalSubmitActionLabel(__('Remove'))
                            ->successNotificationTitle(__('Removed'))
                            ->after(function (?Component $livewire): void {
                                RefreshesNavigationChrome::dispatch($livewire);
                            }),
                    ),
                TextColumn::make('line_total')
                    ->label(__('Line total'))
                    ->state(fn (MerchandiseBasketLine $record): float => $record->lineTotal())
                    ->money('GBP')
                    ->alignEnd()
                    ->summarize(
                        Summarizer::make('total')
                            ->label(__('Total'))
                            ->using(fn (Builder $query): float => (float) $query
                                ->join(
                                    'merchandise_item_variants as merchandise_item_variants_summary',
                                    'merchandise_item_variants_summary.id',
                                    '=',
                                    'merchandise_basket_lines.merchandise_item_variant_id',
                                )
                                ->sum(DB::raw('merchandise_item_variants_summary.price * merchandise_basket_lines.quantity')))
                            ->money('GBP', locale: 'en_GB'),
                    ),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('Your basket is empty'))
            ->emptyStateDescription(__('Browse the shop and add items to get started.'));
    }

    private static function productUrl(MerchandiseBasketLine $record): ?string
    {
        if ($record->item === null) {
            return null;
        }

        return MerchandiseShopResource::getUrl('view', ['record' => $record->item]);
    }
}
