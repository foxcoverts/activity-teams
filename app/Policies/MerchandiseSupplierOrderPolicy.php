<?php

namespace App\Policies;

use App\Enums\TeamFeature;
use App\Models\MerchandiseSupplierOrder;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class MerchandiseSupplierOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageSupplierOrders($user, $this->currentTeam());
    }

    public function view(User $user, MerchandiseSupplierOrder $merchandiseSupplierOrder): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $merchandiseSupplierOrder->loadMissing('team');

        return $merchandiseSupplierOrder->team instanceof Team
            && $this->canManageSupplierOrders($user, $merchandiseSupplierOrder->team);
    }

    public function create(User $user): bool
    {
        return $this->canManageSupplierOrders($user, $this->currentTeam());
    }

    public function update(User $user, MerchandiseSupplierOrder $merchandiseSupplierOrder): bool
    {
        return $this->view($user, $merchandiseSupplierOrder)
            && $merchandiseSupplierOrder->isDraft();
    }

    public function delete(User $user, MerchandiseSupplierOrder $merchandiseSupplierOrder): bool
    {
        return $this->update($user, $merchandiseSupplierOrder);
    }

    public function send(User $user, MerchandiseSupplierOrder $merchandiseSupplierOrder): bool
    {
        return $this->update($user, $merchandiseSupplierOrder)
            && $merchandiseSupplierOrder->lines()->exists();
    }

    public function recordGoodsIn(User $user, MerchandiseSupplierOrder $merchandiseSupplierOrder): bool
    {
        return $this->view($user, $merchandiseSupplierOrder)
            && $merchandiseSupplierOrder->isSent();
    }

    private function canManageSupplierOrders(User $user, ?Team $team): bool
    {
        if ($user->isSuspended() || ! $team instanceof Team) {
            return false;
        }

        return $team->featureEnabled(TeamFeature::Shop)
            && $user->manages($team);
    }

    private function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
