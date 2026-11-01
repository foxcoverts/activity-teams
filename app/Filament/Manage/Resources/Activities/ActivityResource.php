<?php

namespace App\Filament\Manage\Resources\Activities;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\Audience;
use App\Models\Team;
use App\Models\User;
use App\Support\Palette;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Setup;

    protected static ?int $navigationSort = 1;

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

        return $query
            ->whereHas(
                'teams',
                fn (Builder $teams) => $teams->whereKey($team->getKey()),
            )
            ->with([
                'teams' => fn (BelongsToMany $teams) => $teams->whereKey($team->getKey()),
                'activityAudiences.audience',
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
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
                TextColumn::make('default_audience')
                    ->label(__('Default audience'))
                    ->state(fn (Activity $record): string => static::defaultAudience($record)?->name ?? __('Set default audience'))
                    ->action(
                        Action::make('setDefaultAudience')
                            ->label(__('Default audience'))
                            ->authorize(fn (): bool => static::canViewAny())
                            ->fillForm(fn (Activity $record): array => [
                                'default_audience_id' => static::defaultAudience($record)?->getKey(),
                            ])
                            ->schema([
                                Select::make('default_audience_id')
                                    ->label(__('Default audience'))
                                    ->options(fn (Activity $record): array => $record->activityAudiences
                                        ->sortBy(fn ($pairing) => $pairing->audience?->name)
                                        ->mapWithKeys(fn ($pairing): array => [
                                            (string) $pairing->audience_id => (string) $pairing->audience?->name,
                                        ])
                                        ->all())
                                    ->placeholder(__('None')),
                            ])
                            ->action(function (Activity $record, array $data): void {
                                $team = static::currentTeam();
                                abort_unless($team instanceof Team, 404);

                                $audienceId = $data['default_audience_id'] ?? null;
                                $audience = filled($audienceId)
                                    ? Audience::query()->find($audienceId)
                                    : null;

                                $team->setDefaultAudience($record, $audience instanceof Audience ? $audience : null);
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

    private static function defaultAudience(Activity $activity): ?Audience
    {
        $team = static::currentTeam();

        return $team instanceof Team ? $team->defaultAudienceFor($activity) : null;
    }
}
