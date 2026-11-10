<?php

namespace App\Filament\Member\Widgets;

use App\Models\User;

class AllCaughtUpWidget extends DashboardSection
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.member.widgets.all-caught-up';

    protected function sectionData(): array
    {
        $user = auth()->user();
        assert($user instanceof User);

        return [
            'caughtUp' => ! $this->dashboard()->hasAttention(),
            'bookingsUrl' => null,
            'hasTeams' => $user->teams()->exists(),
        ];
    }
}
