<?php

namespace App\Filament\Manage\Resources\TeamVenues\Pages;

use App\Filament\Manage\Resources\TeamVenues\TeamVenueResource;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListTeamVenues extends ListRecords
{
    protected static string $resource = TeamVenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('give')
                ->label('Give a venue')
                ->schema([
                    Select::make('venue_id')
                        ->label('Venue')
                        ->options(function (): array {
                            $team = TeamVenueResource::currentTeam();

                            if ($team === null) {
                                return [];
                            }

                            return Venue::query()
                                ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->required(),
                ])
                ->action(function (array $data): void {
                    TeamVenueResource::currentTeam()
                        ?->venues()
                        ->syncWithoutDetaching([$data['venue_id']]);
                }),
        ];
    }
}
