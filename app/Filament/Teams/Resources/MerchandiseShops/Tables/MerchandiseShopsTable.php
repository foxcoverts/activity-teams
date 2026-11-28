<?php

namespace App\Filament\Teams\Resources\MerchandiseShops\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MerchandiseShopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
            ]);
    }
}
