<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\Tables;

use App\Models\MerchandiseOrder;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class MerchandiseOrdersTable
{
    public static function configure(Table $table): Table
    {
        $tenant = Filament::getTenant();
        $showMember = $tenant && auth()->user()?->manages($tenant);

        return $table
            ->columns([
                TextColumn::make('submitted_at')
                    ->label(__('Submitted'))
                    ->dateTime('j M Y H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('Member'))
                    ->visible((bool) $showMember)
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('total')
                    ->label(__('Total'))
                    ->state(fn (MerchandiseOrder $record): string => Number::currency($record->total(), 'GBP', 'en_GB')),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('submitted_at', 'desc');
    }
}
