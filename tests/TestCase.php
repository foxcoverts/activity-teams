<?php

namespace Tests;

use App\Models\Team;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        $result = parent::actingAs($user, $guard);

        if (Filament::getCurrentPanel()?->getId() === 'teams') {
            $team = $user->managedTeams()->first();

            if ($team === null) {
                $team = $user->teams()->first();
            }

            if ($team === null && $user->isSystemAdmin()) {
                $team = Team::first();
            }

            Filament::setTenant($team instanceof Team ? $team : null);
        }

        return $result;
    }
}
