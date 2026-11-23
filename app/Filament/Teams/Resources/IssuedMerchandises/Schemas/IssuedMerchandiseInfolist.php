<?php

namespace App\Filament\Teams\Resources\IssuedMerchandises\Schemas;

use App\Filament\Support\Infolists\PersonEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class IssuedMerchandiseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Issued item'))
                    ->icon(Heroicon::OutlinedGift)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        PersonEntry::make('user.name')
                            ->label(__('Member')),
                        TextEntry::make('variant.item.title')
                            ->label(__('Item')),
                        TextEntry::make('variant.label')
                            ->label(__('Variant')),
                        TextEntry::make('issued_on')
                            ->label(__('Issued on'))
                            ->date(),
                        TextEntry::make('source')
                            ->label(__('Source'))
                            ->badge(),
                        TextEntry::make('note')
                            ->label(__('Note'))
                            ->placeholder('-')
                            ->prose(),
                    ]),
            ]);
    }
}
