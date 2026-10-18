<?php

namespace App\Filament\Manage\Resources\TeamActivities;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\TeamActivities\Pages\ListTeamActivities;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Activities';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $slug = 'team-activities';

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
        $query = parent::getEloquentQuery();
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
            'teams',
            fn (Builder $teams) => $teams->whereKey($team->getKey()),
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug'),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Remove from team')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Activity $record): void {
                        static::currentTeam()?->activities()->detach($record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamActivities::route('/'),
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
