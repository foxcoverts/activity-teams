<?php

namespace App\Filament\Teams\Resources\MerchandiseSupplierOrders\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MerchandiseSupplierOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.name')
                    ->label(__('Supplier'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->label(__('Sent'))
                    ->dateTime('j M Y H:i')
                    ->placeholder(__('Draft'))
                    ->sortable(),
                TextColumn::make('lines_count')
                    ->label(__('Lines'))
                    ->counts('lines')
                    ->sortable(),
            ])
            ->defaultSort('sent_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
