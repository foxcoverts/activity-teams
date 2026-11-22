<?php

namespace App\Filament\Admin\Resources\Venues\RelationManagers;

use App\Filament\Support\RefreshesNavigationChrome;
use App\Filament\Teams\Resources\Venues\VenueResource as TeamsVenueResource;
use App\Models\Team;
use App\Models\Venue;
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
            ->recordUrl(fn (Team $record): string => TeamsVenueResource::getUrl(
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
                        $venue = $this->getOwnerRecord();

                        if (! $venue instanceof Venue) {
                            return null;
                        }

                        return $record->default_venue_id === $venue->getKey()
                            ? __('Default venue')
                            : null;
                    })
                    ->badge()
                    ->color(Palette::positive())
                    ->placeholder('-'),
            ])
            ->headerActions([
                Action::make('give')
                    ->label(__('Give to a team'))
                    ->modalHeading(__('Give to a team'))
                    ->modalIcon(Heroicon::OutlinedUserGroup)
                    ->schema([
                        Select::make('team_id')
                            ->label(__('Team'))
                            ->options(fn (): array => $this->availableTeamOptions())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('set_as_default', $this->teamNeedsDefaultVenue($state));
                            })
                            ->required(),
                        Toggle::make('set_as_default')
                            ->label(__('Use as this team\'s default venue'))
                            ->disabled(fn (Get $get): bool => $this->teamNeedsDefaultVenue($get('team_id')))
                            ->dehydrated(),
                    ])
                    ->action(function (array $data, Component $livewire): void {
                        $venue = $this->getOwnerRecord();

                        if (! $venue instanceof Venue) {
                            return;
                        }

                        $team = Team::query()->findOrFail($data['team_id']);
                        $hadDefault = $team->default_venue_id !== null;

                        $team->addVenue($venue);

                        if ((bool) ($data['set_as_default'] ?? false) && $hadDefault) {
                            $team->setDefaultVenue($venue);
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
                        $venue = $this->getOwnerRecord();

                        if (! $venue instanceof Venue) {
                            return;
                        }

                        $record->removeVenue($venue);

                        RefreshesNavigationChrome::dispatch($livewire);
                    }),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function availableTeamOptions(): array
    {
        $venue = $this->getOwnerRecord();

        if (! $venue instanceof Venue) {
            return [];
        }

        return Team::query()
            ->whereDoesntHave('venues', fn ($venues) => $venues->whereKey($venue->getKey()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function teamNeedsDefaultVenue(mixed $teamId): bool
    {
        if (blank($teamId)) {
            return true;
        }

        $team = Team::query()->find($teamId);

        return ! $team instanceof Team || $team->default_venue_id === null;
    }
}
