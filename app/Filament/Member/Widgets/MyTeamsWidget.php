<?php

namespace App\Filament\Member\Widgets;

use App\Filament\Teams\Pages\Dashboard as TeamsDashboard;
use App\Models\Team;
use App\Models\User;

class MyTeamsWidget extends DashboardSection
{
    protected static ?int $sort = 8;

    protected string $view = 'filament.member.widgets.my-teams';

    protected function sectionData(): array
    {
        $user = auth()->user();
        assert($user instanceof User);

        $teams = $user->teams()->orderBy('name')->get();

        return [
            'teams' => $teams
                ->map(fn (Team $team): array => [
                    'team' => $team,
                    'role' => $user->roleOn($team),
                    'url' => TeamsDashboard::getUrl(panel: 'teams', tenant: $team),
                ])
                ->values(),
        ];
    }
}
