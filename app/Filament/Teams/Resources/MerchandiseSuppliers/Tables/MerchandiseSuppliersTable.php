<?php

namespace App\Filament\Teams\Resources\MerchandiseSuppliers\Tables;

use App\Models\MerchandiseSupplier;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MerchandiseSuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->description(fn (MerchandiseSupplier $record): ?string => filled($record->notes)
                        ? str($record->notes)->stripTags()->limit(80)->toString()
                        : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label(__('Items'))
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
