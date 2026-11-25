<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\Schemas;

use App\Models\MerchandiseOrder;
use App\Models\MerchandiseOrderLine;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class MerchandiseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Order'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge(),
                        TextEntry::make('submitted_at')
                            ->label(__('Submitted'))
                            ->dateTime('j M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('user.name')
                            ->label(__('Member')),
                        TextEntry::make('total')
                            ->label(__('Total'))
                            ->state(fn (MerchandiseOrder $record): string => Number::currency($record->total(), 'GBP', 'en_GB')),
                        RepeatableEntry::make('lines')
                            ->label(__('Lines'))
                            ->schema([
                                TextEntry::make('item_title')
                                    ->label(__('Item'))
                                    ->formatStateUsing(function (mixed $state, MerchandiseOrderLine $record): string {
                                        $title = (string) $state;
                                        $parts = collect($record->personalisationSnapshot())
                                            ->map(fn (array $row): string => $row['label'].': '.$row['value']);

                                        return $parts->isEmpty()
                                            ? $title
                                            : $title.' ('.$parts->implode(' · ').')';
                                    }),
                                TextEntry::make('variant_label')
                                    ->label(__('Variant')),
                                TextEntry::make('unit_price')
                                    ->label(__('Price'))
                                    ->money('GBP'),
                                TextEntry::make('quantity')
                                    ->label(__('Qty')),
                                TextEntry::make('line_total')
                                    ->label(__('Line total'))
                                    ->state(fn (MerchandiseOrderLine $record): string => Number::currency($record->lineTotal(), 'GBP', 'en_GB')),
                            ]),
                    ]),
            ]);
    }
}
