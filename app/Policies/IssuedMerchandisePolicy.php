<?php

namespace App\Policies;

use App\Enums\TeamFeature;
use App\Models\IssuedMerchandise;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class IssuedMerchandisePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $team = $this->currentTeam();

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Shop)
            && $user->manages($team);
    }

    public function view(User $user, IssuedMerchandise $issuedMerchandise): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $issuedMerchandise->loadMissing('team');

        return $issuedMerchandise->team instanceof Team
            && $issuedMerchandise->team->featureEnabled(TeamFeature::Shop)
            && $user->manages($issuedMerchandise->team);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, IssuedMerchandise $issuedMerchandise): bool
    {
        return $this->view($user, $issuedMerchandise);
    }

    public function delete(User $user, IssuedMerchandise $issuedMerchandise): bool
    {
        return $this->update($user, $issuedMerchandise);
    }

    private function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
