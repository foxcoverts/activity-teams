<?php

namespace App\Filament\Teams\Resources\Activities\Actions;

use App\Filament\Support\RefreshesNavigationChrome;
use App\Filament\Teams\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;

class GiveActivityAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'give';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => auth()->user()?->isSystemAdmin() ? 'Give an activity' : 'Add an activity')
            ->modalHeading(fn (): string => auth()->user()?->isSystemAdmin() ? __('Give an activity') : __('Add an activity'))
            ->modalIcon(Heroicon::OutlinedFlag)
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
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data, Action $action): void {
                $team = ActivityResource::currentTeam();
                abort_unless($team !== null, 404);

                $activity = Activity::query()
                    ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                    ->findOrFail($data['activity_id']);

                $team->addActivity($activity);

                RefreshesNavigationChrome::dispatch($action->getLivewire());
            });
    }
}
