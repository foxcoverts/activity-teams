<?php

namespace App\Filament\Teams\Resources\MerchandiseSuppliers\Schemas;

use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MerchandiseSupplierForm
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
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        MarkdownEditor::make('notes')
                            ->label(__('Notes'))
                            ->helperText(__('Anything useful when ordering — contact email, B2B portal, account numbers, lead times, and so on.'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
