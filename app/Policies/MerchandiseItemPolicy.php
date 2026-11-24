<?php

namespace App\Policies;

use App\Enums\MerchandiseAvailability;
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
            && ($user->isCurrentMemberOf($team) || $this->canManageShopCatalogue($user, $team));
    }

    public function view(User $user, MerchandiseItem $merchandiseItem): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $merchandiseItem->loadMissing('team');

        $team = $merchandiseItem->team;

        if (! $team instanceof Team || ! $team->featureEnabled(TeamFeature::Shop)) {
            return false;
        }

        if ($this->canManageShopCatalogue($user, $team)) {
            return true;
        }

        return $merchandiseItem->availability === MerchandiseAvailability::Available
            && $user->isCurrentMemberOf($team);
    }

    public function create(User $user): bool
    {
        return $this->canManageShopCatalogue($user, $this->currentTeam());
    }

    public function update(User $user, MerchandiseItem $merchandiseItem): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $merchandiseItem->loadMissing('team');

        return $merchandiseItem->team instanceof Team
            && $this->canManageShopCatalogue($user, $merchandiseItem->team);
    }

    public function delete(User $user, MerchandiseItem $merchandiseItem): bool
    {
        return $this->update($user, $merchandiseItem);
    }

    private function canManageShopCatalogue(User $user, ?Team $team): bool
    {
        if ($user->isSuspended() || ! $team instanceof Team) {
            return false;
        }

        return $team->featureEnabled(TeamFeature::Shop)
            && ($user->isSystemAdmin() || $user->manages($team));
    }

    private function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
