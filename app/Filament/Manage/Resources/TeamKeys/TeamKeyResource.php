<?php

namespace App\Filament\Manage\Resources\TeamKeys;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\TeamKeys\Pages\ListTeamKeys;
use App\Models\Key;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamKeyResource extends Resource
{
    protected static ?string $model = Key::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Keys';

    protected static ?string $modelLabel = 'key';

    protected static ?string $slug = 'team-keys';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return static::currentManager() instanceof User;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['requirements.activity', 'requirements.venue'])
            ->orderBy('name');
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        return $query->relevantTo($team);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Key'))
                    ->searchable(),
                TextColumn::make('required_for')
                    ->label(__('Required for'))
                    ->state(fn (Key $record): array => $record->requirements->map->summary()->all())
                    ->listWithLineBreaks(),
                TextColumn::make('holders')
                    ->label(__('Key rings'))
                    ->state(fn (Key $record): array => $record->accessRingDescriptions())
                    ->listWithLineBreaks(),
            ])
            ->recordUrl(null)
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamKeys::route('/'),
        ];
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }

    public static function currentManager(): ?User
    {
        $user = auth()->user();
        $team = static::currentTeam();

        if (! $user instanceof User || ! $team instanceof Team || ! $user->manages($team)) {
            return null;
        }

        return $user;
    }
}
