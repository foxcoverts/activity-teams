<?php

namespace App\Filament\Admin\Resources\Activities\RelationManagers;

use App\Filament\Support\RefreshesNavigationChrome;
use App\Filament\Teams\Resources\Activities\ActivityResource as TeamsActivityResource;
use App\Models\Activity;
use App\Models\Audience;
use App\Models\Team;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class TeamsRelationManager extends RelationManager
{
    protected static string $relationship = 'teams';

    protected static ?string $title = 'Teams';

    protected static ?bool $isBadgeLazyLoaded = true;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->teams()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->recordUrl(fn (Team $record): string => TeamsActivityResource::getUrl(
                'index',
                panel: 'teams',
                tenant: $record,
            ))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('team_default')
                    ->label('')
                    ->state(function (Team $record): ?string {
                        $activity = $this->getOwnerRecord();

                        if (! $activity instanceof Activity) {
                            return null;
                        }

                        return $record->default_activity_id === $activity->getKey()
                            ? __('Default activity')
                            : null;
                    })
                    ->badge()
                    ->color(Palette::positive())
                    ->placeholder('-'),
                TextColumn::make('default_audience')
                    ->label(__('Default audience'))
                    ->state(function (Team $record): ?string {
                        $activity = $this->getOwnerRecord();

                        if (! $activity instanceof Activity) {
                            return null;
                        }

                        return $record->defaultAudienceFor($activity)?->name;
                    })
                    ->placeholder('-'),
            ])
            ->headerActions([
                Action::make('give')
                    ->label(__('Give to a team'))
                    ->modalHeading(__('Give to a team'))
                    ->modalIcon(Heroicon::OutlinedUserGroup)
                    ->visible(function (): bool {
                        $activity = $this->getOwnerRecord();

                        return $activity instanceof Activity && $activity->hasAudiences();
                    })
                    ->schema([
                        Select::make('team_id')
                            ->label(__('Team'))
                            ->options(fn (): array => $this->availableTeamOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('set_as_default', $this->teamNeedsDefaultActivity($state));
                            })
                            ->required(),
                        Select::make('default_audience_id')
                            ->label(__('Default audience'))
                            ->options(fn (): array => $this->audienceOptions())
                            ->placeholder(__('None'))
                            ->disabled(fn (): bool => $this->audienceOptions() === []),
                        Toggle::make('set_as_default')
                            ->label(__('Use as this team\'s default activity'))
                            ->disabled(fn (Get $get): bool => $this->teamNeedsDefaultActivity($get('team_id')))
                            ->dehydrated(),
                    ])
                    ->action(function (array $data, Component $livewire): void {
                        $activity = $this->getOwnerRecord();

                        if (! $activity instanceof Activity) {
                            return;
                        }

                        $team = Team::query()->findOrFail($data['team_id']);
                        $hadDefault = $team->default_activity_id !== null;

                        $team->addActivity($activity);

                        if ((bool) ($data['set_as_default'] ?? false) && $hadDefault) {
                            $team->setDefaultActivity($activity);
                        }

                        $audienceId = $data['default_audience_id'] ?? null;

                        if (filled($audienceId)) {
                            $audience = Audience::query()->find($audienceId);
                            $team->setDefaultAudience($activity, $audience instanceof Audience ? $audience : null);
                        }

                        RefreshesNavigationChrome::dispatch($livewire);
                    }),
            ])
            ->recordActions([
                Action::make('remove')
                    ->label(__('Remove from team'))
                    ->color(Palette::destructive())
                    ->requiresConfirmation()
                    ->action(function (Team $record, Component $livewire): void {
                        $activity = $this->getOwnerRecord();

                        if (! $activity instanceof Activity) {
                            return;
                        }

                        $record->removeActivity($activity);

                        RefreshesNavigationChrome::dispatch($livewire);
                    }),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function availableTeamOptions(): array
    {
        $activity = $this->getOwnerRecord();

        if (! $activity instanceof Activity) {
            return [];
        }

        return Team::query()
            ->whereDoesntHave('activities', fn ($activities) => $activities->whereKey($activity->getKey()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function audienceOptions(): array
    {
        $activity = $this->getOwnerRecord();

        if (! $activity instanceof Activity) {
            return [];
        }

        $activity->loadMissing('activityAudiences.audience');

        return $activity->activityAudiences
            ->sortBy(fn ($pairing) => $pairing->audience?->name)
            ->mapWithKeys(fn ($pairing): array => [
                (string) $pairing->audience_id => (string) $pairing->audience?->name,
            ])
            ->all();
    }

    private function teamNeedsDefaultActivity(mixed $teamId): bool
    {
        if (blank($teamId)) {
            return true;
        }

        $team = Team::query()->find($teamId);

        return ! $team instanceof Team || $team->default_activity_id === null;
    }
}
