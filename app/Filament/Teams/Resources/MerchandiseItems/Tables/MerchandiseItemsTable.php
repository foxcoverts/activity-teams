<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Tables;

use App\Enums\MerchandiseAvailability;
use App\Models\MerchandiseItem;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
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
                ImageColumn::make('thumbnail')
                    ->label(__('Image'))
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->getStateUsing(fn (MerchandiseItem $record): ?string => $record->thumbnailImage()?->path),
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->description(fn (MerchandiseItem $record): ?string => $record->description)
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
