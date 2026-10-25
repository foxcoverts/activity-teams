<?php

namespace App\Filament\Manage\Resources\Activities;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use App\Support\Palette;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?string $navigationLabel = 'Activities';

    protected static ?string $modelLabel = 'activity';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        $team = static::currentTeam();

        return $user instanceof User
            && $team instanceof Team
            && ($user->manages($team) || $user->isSystemAdmin());
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
                TextColumn::make('default_activity')
                    ->label(__('Default'))
                    ->state(fn (Activity $record): string => static::currentTeam()?->default_activity_id === $record->getKey()
                        ? __('Default')
                        : __('Set as default'))
                    ->badge(fn (Activity $record): bool => static::currentTeam()?->default_activity_id === $record->getKey())
                    ->color(fn (Activity $record): string|array => static::currentTeam()?->default_activity_id === $record->getKey()
                        ? Palette::positive()
                        : 'primary')
                    ->extraAttributes(fn (Activity $record): array => static::currentTeam()?->default_activity_id === $record->getKey()
                        ? []
                        : ['class' => 'team-default-action'])
                    ->disabledClick(fn (Activity $record): bool => static::currentTeam()?->default_activity_id === $record->getKey())
                    ->action(
                        Action::make('setDefault')
                            ->label('Set as default')
                            ->color('primary')
                            ->authorize(fn (): bool => static::canViewAny())
                            ->visible(fn (Activity $record): bool => static::currentTeam()?->default_activity_id !== $record->getKey())
                            ->action(function (Activity $record): void {
                                $team = static::currentTeam();
                                abort_unless($team instanceof Team, 404);

                                $team->setDefaultActivity($record);
                            }),
                    ),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label('Remove from team')
                    ->color(Palette::destructive())
                    ->requiresConfirmation()
                    ->action(function (Activity $record): void {
                        static::currentTeam()?->removeActivity($record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
