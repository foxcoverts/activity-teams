<?php

namespace App\Policies;

use App\Enums\TeamFeature;
use App\Models\MerchandiseItem;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class MerchandiseItemPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $team = $this->currentTeam();

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Shop)
            && ($user->isSystemAdmin() || $user->manages($team));
    }

    public function view(User $user, MerchandiseItem $merchandiseItem): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $merchandiseItem->loadMissing('team');

        if (! $merchandiseItem->team instanceof Team || ! $merchandiseItem->team->featureEnabled(TeamFeature::Shop)) {
            return false;
        }

        return $user->isSystemAdmin() || $user->manages($merchandiseItem->team);
    }

    public function create(User $user): bool
    {
        return $this->canManageShopCatalogue($user);
    }

    public function update(User $user, MerchandiseItem $merchandiseItem): bool
    {
        return $this->view($user, $merchandiseItem);
    }

    public function delete(User $user, MerchandiseItem $merchandiseItem): bool
    {
        return $this->update($user, $merchandiseItem);
    }

    private function canManageShopCatalogue(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $team = $this->currentTeam();

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Shop)
            && ($user->isSystemAdmin() || $user->manages($team));
    }

    private function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
