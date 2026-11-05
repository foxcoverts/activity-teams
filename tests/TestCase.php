<?php

namespace Tests;

use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        $result = parent::actingAs($user, $guard);

        if ($user instanceof User && Filament::getCurrentPanel()?->getId() === 'manage') {
            $team = $user->managedTeams()->first();

            if ($team === null && $user->isSystemAdmin()) {
                $team = $user->getTenants(Filament::getCurrentPanel())->first();
            }

            Filament::setTenant($team instanceof Team ? $team : null);
        } elseif ($user instanceof User && Filament::getCurrentPanel()?->getId() === 'teams') {
            $team = $user->teams()->first();

            Filament::setTenant($team instanceof Team ? $team : null);
        }

        return $result;
    }
}
