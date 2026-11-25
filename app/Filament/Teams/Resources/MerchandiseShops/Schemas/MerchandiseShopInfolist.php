<?php

namespace App\Filament\Teams\Resources\MerchandiseShops\Schemas;

use App\Models\MerchandiseItem;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MerchandiseShopInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn (MerchandiseItem $record): string => $record->title)
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('images')
                            ->label(__('Gallery'))
                            ->schema([
                                ImageEntry::make('path')
                                    ->hiddenLabel()
                                    ->imageHeight(240)
                                    ->getStateUsing(fn ($record): ?string => $record->url()),
                            ])
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->placeholder('-')
                            ->prose(),
                        RepeatableEntry::make('variants')
                            ->label(__('Variants'))
                            ->schema([
                                TextEntry::make('label')
                                    ->label(__('Variant')),
                                TextEntry::make('price')
                                    ->label(__('Price'))
                                    ->money('GBP'),
                            ]),
                    ]),
            ]);
    }
}
