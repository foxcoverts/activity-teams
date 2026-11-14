<?php

namespace App\Policies;

use App\Enums\TeamFeature;
use App\Models\EquipmentSetGroup;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class EquipmentSetGroupPolicy
{
    public function viewAny(User $user): bool
    {
        $team = $this->currentTeam();

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Debrief)
            && ($user->manages($team) || $user->isSystemAdmin());
    }

    public function view(User $user, EquipmentSetGroup $equipmentSetGroup): bool
    {
        return $this->manageGroup($user, $equipmentSetGroup);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, EquipmentSetGroup $equipmentSetGroup): bool
    {
        return $this->manageGroup($user, $equipmentSetGroup);
    }

    public function delete(User $user, EquipmentSetGroup $equipmentSetGroup): bool
    {
        if (! $this->manageGroup($user, $equipmentSetGroup)) {
            return false;
        }

        $equipmentSetGroup->loadMissing('team');
        $team = $equipmentSetGroup->team;

        if (! $team instanceof Team) {
            return false;
        }

        return $team->equipmentSetGroups()->count() > 1;
    }

    private function manageGroup(User $user, EquipmentSetGroup $equipmentSetGroup): bool
    {
        $equipmentSetGroup->loadMissing('team');
        $team = $equipmentSetGroup->team;

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
