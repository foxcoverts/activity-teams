<?php

namespace App\Filament\Teams\Resources\EquipmentSets\Schemas;

use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use App\Models\Activity;
use App\Models\EquipmentSet;
use App\Models\EquipmentSetGroup;
use App\Models\Team;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Unique;

class EquipmentSetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Equipment set'))
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->columns(1)
                ->columnSpanFull()
                ->schema(static::fields()),
        ]);
    }

    /**
     * @return list<Component>
     */
    public static function fields(?string $lockedGroupId = null, ?EquipmentSet $record = null): array
    {
        $fields = [
            TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(255)
                ->rule(function () use ($record): Unique {
                    $rule = (new Unique('equipment_sets', 'name'));
                    $team = EquipmentSetResource::currentTeam();

                    if ($team instanceof Team) {
                        $rule->where('team_id', $team->getKey());
                    }

                    if ($record instanceof EquipmentSet) {
                        $rule->ignore($record->getKey());
                    }

                    return $rule;
                }),
        ];

        if ($lockedGroupId !== null) {
            $fields[] = Hidden::make('equipment_set_group_id')
                ->default($lockedGroupId)
                ->required();
        } else {
            $fields[] = Select::make('equipment_set_group_id')
                ->label(__('Group'))
                ->required()
                ->options(function (): array {
                    $team = EquipmentSetResource::currentTeam();

                    if (! $team instanceof Team) {
                        return [];
                    }

                    EquipmentSetGroup::ensureDefaultFor($team);

                    return $team->equipmentSetGroups()
                        ->orderBy('rank')
                        ->pluck('label', 'id')
                        ->all();
                })
                ->default(function (): ?string {
                    $team = EquipmentSetResource::currentTeam();

                    if (! $team instanceof Team) {
                        return null;
                    }

                    return EquipmentSetGroup::ensureDefaultFor($team)->getKey();
                })
                ->searchable()
                ->preload();
        }

        $fields[] = Select::make('activities')
            ->label(__('Activities'))
            ->helperText(__('Shown on debriefs for these activities. Leave empty to keep the set unused until linked.'))
            ->multiple()
            ->options(function (): array {
                $team = EquipmentSetResource::currentTeam();

                if (! $team instanceof Team) {
                    return [];
                }

                return Activity::query()
                    ->whereHas('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all();
            })
            ->searchable()
            ->preload();

        return $fields;
    }
}
