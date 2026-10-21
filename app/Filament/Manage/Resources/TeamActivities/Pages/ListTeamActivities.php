<?php

namespace App\Filament\Manage\Resources\TeamActivities\Pages;

use App\Filament\Manage\Resources\TeamActivities\TeamActivityResource;
use App\Models\Activity;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListTeamActivities extends ListRecords
{
    protected static string $resource = TeamActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('give')
                ->label('Give an activity')
                ->authorize(fn (): bool => TeamActivityResource::canViewAny())
                ->schema([
                    Select::make('activity_id')
                        ->label('Activity')
                        ->options(function (): array {
                            $team = TeamActivityResource::currentTeam();

                            if ($team === null) {
                                return [];
                            }

                            return Activity::query()
                                ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $team = TeamActivityResource::currentTeam();
                    abort_unless($team !== null, 404);

                    $activity = Activity::query()
                        ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                        ->findOrFail($data['activity_id']);

                    $team->addActivity($activity);
                }),
        ];
    }
}
