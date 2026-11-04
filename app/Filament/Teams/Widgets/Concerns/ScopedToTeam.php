<?php

namespace App\Filament\Teams\Widgets\Concerns;

use App\Models\Team;
use App\Models\User;
use App\Support\MemberDashboard;
use Filament\Facades\Filament;

trait ScopedToTeam
{
    protected function dashboard(): MemberDashboard
    {
        $user = auth()->user();
        $team = Filament::getTenant();
        assert($user instanceof User && $team instanceof Team);

        return app(MemberDashboard::class, ['user' => $user, 'team' => $team]);
    }
}
