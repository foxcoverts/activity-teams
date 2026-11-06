<?php

namespace App\Filament\Teams\Resources\Keys\Tables;

use App\Filament\Keys\PassKeyAction;
use App\Filament\Teams\Resources\Keys\KeyResource;
use App\Models\Key;
use App\Models\Team;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->label(__('Key'))
                    ->searchable(),
                TextColumn::make('holder.name')
                    ->label(__('Holder'))
                    ->placeholder(__('In the cupboard')),
                TextColumn::make('unlocks')
                    ->label(__('Unlocks'))
                    ->state(fn (Key $record): array => $record->lockNames())
                    ->listWithLineBreaks()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('holder_id')
                    ->label(__('Holder'))
                    ->relationship(
                        'holder',
                        'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $team = KeyResource::currentTeam();

                            $query->where('users.suspended', false);

                            if (! $team instanceof Team) {
                                return $query->whereRaw('0 = 1');
                            }

                            return $query->whereHas(
                                'keys',
                                fn (Builder $keys) => $keys->relevantTo($team),
                            );
                        },
                        hasEmptyOption: true,
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                PassKeyAction::make(),
            ])
            ->toolbarActions([]);
    }
}
