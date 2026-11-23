<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Tables;

use App\Enums\MerchandiseAvailability;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MerchandiseItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->description(fn ($record): ?string => $record->description)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('availability')
                    ->label(__('Availability'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label(__('Variants')),
            ])
            ->filters([
                SelectFilter::make('availability')
                    ->label(__('Availability'))
                    ->options(MerchandiseAvailability::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
