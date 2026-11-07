<?php

namespace App\Filament\Admin\Resources\Teams\Schemas;

use App\Enums\BrandColor;
use App\Enums\MembershipRole;
use App\Support\Avatars;
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
                        Select::make('color')
                            ->label(__('Colour'))
                            ->options(fn (): array => collect(BrandColor::cases())
                                ->mapWithKeys(fn (BrandColor $color): array => [
                                    $color->value => '<span class="inline-flex items-center gap-2">'
                                        .'<img src="'.e(Avatars::brandSwatchUrl($color, 24)).'" alt="" width="20" height="20" class="size-5 rounded" />'
                                        .e($color->getLabel())
                                        .'</span>',
                                ])
                                ->all())
                            ->allowHtml()
                            ->native(false)
                            ->searchable()
                            ->nullable()
                            ->placeholder(__('Automatic from name'))
                            ->helperText(__('Leave blank to derive from the team name (updates if the name changes). Pick a colour to lock it.')),
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
