<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MerchandiseItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Item'))
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('Title')),
                        TextEntry::make('description')
                            ->label(__('Description'))
                            ->placeholder('-')
                            ->prose(),
                        TextEntry::make('availability')
                            ->label(__('Availability'))
                            ->badge(),
                        RepeatableEntry::make('images')
                            ->label(__('Images'))
                            ->schema([
                                ImageEntry::make('path')
                                    ->label(__('Image'))
                                    ->disk('public')
                                    ->visibility('public')
                                    ->square()
                                    ->imageHeight(120),
                            ])
                            ->placeholder('-'),
                        RepeatableEntry::make('variants')
                            ->label(__('Variants'))
                            ->schema([
                                TextEntry::make('label')
                                    ->label(__('Label')),
                                TextEntry::make('price')
                                    ->label(__('Price'))
                                    ->money('GBP'),
                                TextEntry::make('availability')
                                    ->label(__('Availability'))
                                    ->badge(),
                            ]),
                    ]),
            ]);
    }
}
