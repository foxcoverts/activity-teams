<?php

namespace App\Filament\Admin\Resources\Teams;

use App\Enums\MembershipRole;
use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Teams\Pages\CreateTeam;
use App\Filament\Admin\Resources\Teams\Pages\EditTeam;
use App\Filament\Admin\Resources\Teams\Pages\ListTeams;
use App\Filament\Manage\PanelNavigationItem as ManagePanelNavigationItem;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Models\Team;
use App\Support\Palette;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('managers');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('managers.name')
                    ->label('Managers')
                    ->listWithLineBreaks()
                    ->placeholder('No managers')
                    ->url(fn (Team $record, mixed $state): string => MemberResource::getUrl('index', panel: 'manage', tenant: $record)),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('manage')
                    ->label(ManagePanelNavigationItem::label())
                    ->icon(ManagePanelNavigationItem::icon())
                    ->color(Palette::positive())
                    ->url(fn (Team $record): string => Dashboard::getUrl(panel: 'manage', tenant: $record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeams::route('/'),
            'create' => CreateTeam::route('/create'),
            'edit' => EditTeam::route('/{record}/edit'),
        ];
    }
}
