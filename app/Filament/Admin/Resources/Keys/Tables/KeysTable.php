<?php

namespace App\Filament\Admin\Resources\Keys\Tables;

use App\Filament\Support\Tables\PersonColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KeysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                PersonColumn::make('holder.name')
                    ->label(__('Holder'))
                    ->placeholder(__('In the cupboard')),
            ])
            ->filters([
                Filter::make('without_locks')
                    ->label(__('No locks'))
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('locks')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
