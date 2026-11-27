<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Schemas;

use App\Filament\Teams\Resources\MerchandiseSuppliers\MerchandiseSupplierResource;
use App\Models\MerchandiseItem;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
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
                        TextEntry::make('supplier.name')
                            ->label(__('Supplier'))
                            ->placeholder('-')
                            ->url(fn (MerchandiseItem $record): ?string => $record->supplier
                                ? MerchandiseSupplierResource::getUrl('view', ['record' => $record->supplier])
                                : null)
                            ->color('primary'),
                        TextEntry::make('availability')
                            ->label(__('Availability'))
                            ->badge(),
                        RepeatableEntry::make('images')
                            ->label(__('Images'))
                            ->contained(false)
                            ->extraAttributes([
                                'class' => 'merchandise-item-images',
                            ])
                            ->schema([
                                ImageEntry::make('path')
                                    ->hiddenLabel()
                                    ->square()
                                    ->imageHeight(120)
                                    ->extraAttributes([
                                        'class' => 'merchandise-item-image',
                                    ])
                                    ->getStateUsing(fn ($record): ?string => $record->url()),
                            ])
                            ->placeholder('-'),
                    ]),
                Section::make(__('Variants'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'merchandise-item-table-panel',
                    ])
                    ->headerActions([
                        Action::make('viewOptions')
                            ->label(__('Options'))
                            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                            ->visible(fn (MerchandiseItem $record): bool => $record->optionAxes->isNotEmpty())
                            ->modalHeading(__('Options'))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel(__('Close'))
                            ->schema([
                                RepeatableEntry::make('optionAxes')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label(__('Axis')),
                                        TextEntry::make('values_summary')
                                            ->label(__('Values'))
                                            ->state(fn ($record): string => $record->values
                                                ->pluck('label')
                                                ->filter()
                                                ->implode(', ')),
                                    ]),
                            ]),
                    ])
                    ->schema([
                        RepeatableEntry::make('variants')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make(__('Label')),
                                TableColumn::make(__('Price')),
                                TableColumn::make(__('On hand')),
                                TableColumn::make(__('Availability')),
                            ])
                            ->schema([
                                TextEntry::make('label')
                                    ->hiddenLabel(),
                                TextEntry::make('price')
                                    ->hiddenLabel()
                                    ->money('GBP'),
                                TextEntry::make('stock_quantity')
                                    ->label(__('On hand')),
                                TextEntry::make('availability')
                                    ->hiddenLabel()
                                    ->badge(),
                            ]),
                    ]),
                Section::make(__('Personalisation'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'merchandise-item-table-panel',
                    ])
                    ->visible(fn (MerchandiseItem $record): bool => $record->personalisationFieldDefinitions() !== [])
                    ->schema([
                        RepeatableEntry::make('personalisation_fields')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make(__('Field')),
                                TableColumn::make(__('Required')),
                            ])
                            ->schema([
                                TextEntry::make('label')
                                    ->hiddenLabel(),
                                TextEntry::make('required')
                                    ->label(__('Required'))
                                    ->formatStateUsing(fn (mixed $state): string => $state ? __('Yes') : __('No')),
                            ])
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
