<?php

namespace App\Filament\Teams\Resources\EquipmentSets\Schemas;

use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use App\Models\Activity;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EquipmentSetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Equipment set'))
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255)
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: function ($rule) {
                                $team = EquipmentSetResource::currentTeam();

                                return $team === null
                                    ? $rule
                                    : $rule->where('team_id', $team->getKey());
                            },
                        ),
                    Select::make('activities')
                        ->label(__('Activities'))
                        ->helperText(__('Shown on debriefs for these activities. Leave empty to keep the set unused until linked.'))
                        ->multiple()
                        ->relationship(
                            name: 'activities',
                            titleAttribute: 'name',
                            modifyQueryUsing: function ($query) {
                                $team = EquipmentSetResource::currentTeam();

                                if ($team === null) {
                                    return $query->whereRaw('0 = 1');
                                }

                                return $query->whereHas(
                                    'teams',
                                    fn ($teams) => $teams->whereKey($team->getKey()),
                                )->orderBy('name');
                            },
                        )
                        ->preload()
                        ->searchable()
                        ->getOptionLabelFromRecordUsing(fn (Activity $record): string => $record->name),
                ]),
        ]);
    }
}
