<?php

namespace App\Filament\Teams\Resources\MerchandiseSuppliers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MerchandiseSupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Supplier'))
                    ->icon(Heroicon::OutlinedTruck)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Name')),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->markdown()
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('items_count')
                            ->label(__('Catalogue items'))
                            ->counts('items'),
                    ]),
            ]);
    }
}
