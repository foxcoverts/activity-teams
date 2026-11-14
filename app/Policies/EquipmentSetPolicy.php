<?php

namespace App\Policies;

use App\Enums\TeamFeature;
use App\Models\EquipmentSet;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class EquipmentSetPolicy
{
    public function viewAny(User $user): bool
    {
        $team = $this->currentTeam();

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Debrief)
            && ($user->manages($team) || $user->isSystemAdmin());
    }

    public function view(User $user, EquipmentSet $equipmentSet): bool
    {
        return $this->manageSet($user, $equipmentSet);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, EquipmentSet $equipmentSet): bool
    {
        return $this->manageSet($user, $equipmentSet);
    }

    public function delete(User $user, EquipmentSet $equipmentSet): bool
    {
        return $this->manageSet($user, $equipmentSet);
    }

    private function manageSet(User $user, EquipmentSet $equipmentSet): bool
    {
        $equipmentSet->loadMissing('team');
        $team = $equipmentSet->team;

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Debrief)
            && ($user->manages($team) || $user->isSystemAdmin());
    }

    private function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
