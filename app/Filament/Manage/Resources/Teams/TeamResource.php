<?php

namespace App\Filament\Manage\Resources\Teams;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Filament\Manage\Resources\Teams\Pages\CreateTeam;
use App\Filament\Manage\Resources\Teams\Pages\EditTeam;
use App\Filament\Manage\Resources\Teams\Pages\ListTeams;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
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

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->helperText(__('Leave blank to generate from the name.'))
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                Select::make('manager_id')
                    ->label('First manager')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->visible(fn (string $operation): bool => $operation === 'create'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('managers');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('managers.name')
                    ->label('Managers')
                    ->listWithLineBreaks()
                    ->placeholder('No managers')
                    ->url(fn (Team $record, mixed $state): string => MemberResource::getUrl('index', tenant: $record)),
            ])
            ->recordActions([
                EditAction::make(),
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
