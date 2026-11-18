<?php

namespace App\Filament\Teams\Resources\Documents\Tables;

use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
            ])
            ->filters([
                TrashedFilter::make()
                    ->visible(fn (): bool => auth()->user()->manages(Filament::getTenant())),
            ]);
    }
}
