<?php

namespace App\Filament\Teams\Resources\IssuedMerchandises\Tables;

use App\Filament\Support\Tables\PersonColumn;
use App\Models\MerchandiseItem;
use App\Models\Team;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IssuedMerchandisesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('issued_on', 'desc')
            ->columns([
                PersonColumn::make('user.name')
                    ->label(__('Member'))
                    ->searchable(),
                TextColumn::make('variant.item.title')
                    ->label(__('Item'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('variant.label')
                    ->label(__('Variant')),
                TextColumn::make('issued_on')
                    ->label(__('Issued on'))
                    ->date()
                    ->sortable(),
                TextColumn::make('source')
                    ->label(__('Source'))
                    ->badge(),
                TextColumn::make('note')
                    ->label(__('Note'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('merchandise_item_id')
                    ->label(__('Item'))
                    ->options(function (): array {
                        $team = Filament::getTenant();

                        if (! $team instanceof Team) {
                            return [];
                        }

                        return MerchandiseItem::query()
                            ->forTeam($team)
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $itemId = $data['value'] ?? null;

                        if (blank($itemId)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'variant',
                            fn (Builder $variants): Builder => $variants->where('merchandise_item_id', $itemId),
                        );
                    }),
                SelectFilter::make('user_id')
                    ->label(__('Member'))
                    ->relationship('user', 'name', modifyQueryUsing: function (Builder $query): Builder {
                        $team = Filament::getTenant();

                        if (! $team instanceof Team) {
                            return $query->whereRaw('0 = 1');
                        }

                        return $query
                            ->whereHas(
                                'teams',
                                fn (Builder $teams): Builder => $teams->whereKey($team->getKey()),
                            )
                            ->orderBy('name');
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
