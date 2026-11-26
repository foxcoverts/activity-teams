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
                Section::make(__('Details'))
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
                                    ->square()
                                    ->imageHeight(120)
                                    ->getStateUsing(fn ($record): ?string => $record->url()),
                            ])
                            ->placeholder('-'),
                    ]),
                Section::make(__('Variants'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('optionAxes')
                            ->label(__('Options'))
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('Axis')),
                                TextEntry::make('values_summary')
                                    ->label(__('Values'))
                                    ->state(fn ($record): string => $record->values
                                        ->pluck('label')
                                        ->filter()
                                        ->implode(', ')),
                            ])
                            ->placeholder('-'),
                        RepeatableEntry::make('variants')
                            ->label(__('SKUs'))
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
                Section::make(__('Personalisation'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('personalisation_fields')
                            ->label(__('Fields'))
                            ->schema([
                                TextEntry::make('label')
                                    ->label(__('Field')),
                                TextEntry::make('required')
                                    ->label(__('Required'))
                                    ->formatStateUsing(fn (mixed $state): string => $state ? __('Yes') : __('No')),
                            ])
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
