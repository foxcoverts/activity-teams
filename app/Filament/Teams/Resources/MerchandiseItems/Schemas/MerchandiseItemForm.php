<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Schemas;

use App\Enums\MerchandiseAvailability;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MerchandiseItemForm
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
                        TextInput::make('title')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->rows(3),
                        Select::make('availability')
                            ->label(__('Availability'))
                            ->options(MerchandiseAvailability::class)
                            ->required()
                            ->default(MerchandiseAvailability::Available),
                        Repeater::make('variants')
                            ->label(__('Variants'))
                            ->relationship()
                            ->schema([
                                TextInput::make('label')
                                    ->label(__('Label'))
                                    ->helperText(__('e.g. size'))
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->orderColumn('sort')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->validationMessages([
                                'min' => __('You must have at least one variant.'),
                            ])
                            ->addActionLabel(__('Add variant'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
