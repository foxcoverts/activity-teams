<?php

namespace App\Filament\Admin\Resources\Teams\Schemas;

use App\Enums\MembershipRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Team Details'))
                    ->columns(1)->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('slug')
                            ->helperText(__('Leave blank to generate from the name.'))
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->prefix('/')
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        Select::make('manager_id')
                            ->label('First manager')
                            ->searchable()
                            ->relationship('managers', 'name')
                            ->pivotData([
                                'role' => MembershipRole::Manager,
                            ])
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                    ]),
            ]);
    }
}
