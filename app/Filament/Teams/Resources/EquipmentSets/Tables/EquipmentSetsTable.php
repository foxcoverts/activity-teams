<?php

namespace App\Filament\Teams\Resources\EquipmentSets\Tables;

use App\Models\EquipmentSet;
use App\Support\Palette;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EquipmentSetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->defaultGroup(
                Group::make('equipmentSetGroup.label')
                    ->titlePrefixedWithLabel(false)
                    ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin(
                                'equipment_set_groups',
                                'equipment_sets.equipment_set_group_id',
                                '=',
                                'equipment_set_groups.id',
                            )
                            ->orderBy('equipment_set_groups.rank', $direction)
                            ->orderBy('equipment_sets.name')
                            ->select('equipment_sets.*');
                    }),
            )
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('activities.name')
                    ->label(__('Activities'))
                    ->badge()
                    ->getStateUsing(function (EquipmentSet $record): array {
                        $names = $record->activities->pluck('name')->filter()->values()->all();

                        return $names === [] ? [__('None')] : $names;
                    })
                    ->color(fn (string $state): array|string => $state === __('None')
                        ? Palette::destructive()
                        : 'primary'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
