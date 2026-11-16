<?php

namespace App\Filament\Admin\Resources\Teams\Schemas;

use App\Enums\BrandColor;
use App\Enums\MembershipRole;
use App\Enums\TeamFeature;
use App\Models\Team;
use App\Support\Avatars;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                Section::make(__('Features'))
                    ->icon(Heroicon::OutlinedPuzzlePiece)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('features.'.TeamFeature::Tasks->value)
                            ->label(TeamFeature::Tasks->getLabel())
                            ->helperText(__('Show Tasks in the team navigation and allow attaching tasks to bookings.'))
                            ->default(false),
                        Toggle::make('features.'.TeamFeature::Debrief->value)
                            ->label(TeamFeature::Debrief->getLabel())
                            ->helperText(__('Expect a post-session debrief, chase the lead, and show outstanding debriefs to managers. Turning this on starts chasing from now; older sessions can still be debriefed but are not chased.'))
                            ->default(false),
                    ]),
                Section::make(__('Mail'))
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('mail_from_name')
                            ->label(__('From name'))
                            ->placeholder(fn (Get $get, ?Team $record): string => (string) ($get('name') ?: $record?->name ?: ''))
                            ->maxLength(255),
                        TextInput::make('mail_from_local')
                            ->label(__('From address'))
                            ->placeholder(fn (Get $get, ?Team $record): string => (new Team([
                                'name' => $get('name') ?: $record?->name,
                                'slug' => $get('slug') ?: $record?->slug,
                            ]))->defaultMailFromLocal())
                            ->suffix('@'.Team::mailFromDomain())
                            ->maxLength(255)
                            ->rule('regex:/^[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*$/')
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'regex' => __('Enter a valid email local part.'),
                            ]),
                    ]),
            ]);
    }
}
