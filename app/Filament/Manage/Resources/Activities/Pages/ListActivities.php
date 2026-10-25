<?php

namespace App\Filament\Manage\Resources\Activities\Pages;

use App\Filament\Manage\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('give')
                ->label('Give an activity')
                ->authorize(fn (): bool => ActivityResource::canViewAny())
                ->schema([
                    Select::make('activity_id')
                        ->label('Activity')
                        ->options(function (): array {
                            $team = ActivityResource::currentTeam();

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
                    $team = ActivityResource::currentTeam();
                    abort_unless($team !== null, 404);

                    $activity = Activity::query()
                        ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                        ->findOrFail($data['activity_id']);

                    $team->addActivity($activity);
                }),
        ];
    }
}
