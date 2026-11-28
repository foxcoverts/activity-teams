<?php

namespace App\Policies;

use App\Enums\TeamFeature;
use App\Models\MerchandiseSupplier;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class MerchandiseSupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageShopCatalogue($user, $this->currentTeam());
    }

    public function view(User $user, MerchandiseSupplier $merchandiseSupplier): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $merchandiseSupplier->loadMissing('team');

        return $merchandiseSupplier->team instanceof Team
            && $this->canManageShopCatalogue($user, $merchandiseSupplier->team);
    }

    public function create(User $user): bool
    {
        return $this->canManageShopCatalogue($user, $this->currentTeam());
    }

    public function update(User $user, MerchandiseSupplier $merchandiseSupplier): bool
    {
        return $this->view($user, $merchandiseSupplier);
    }

    public function delete(User $user, MerchandiseSupplier $merchandiseSupplier): bool
    {
        if (! $this->update($user, $merchandiseSupplier)) {
            return false;
        }

        return ! $merchandiseSupplier->items()->exists();
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
