<?php

namespace App\Policies;

use App\Enums\TeamFeature;
use App\Models\MerchandiseOrder;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class MerchandiseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $team = $this->currentTeam();

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Shop)
            && $user->isCurrentMemberOf($team);
    }

    public function view(User $user, MerchandiseOrder $merchandiseOrder): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $merchandiseOrder->loadMissing('team');
        $team = $merchandiseOrder->team;

        if (! $team instanceof Team || ! $team->featureEnabled(TeamFeature::Shop)) {
            return false;
        }

        if ($user->manages($team)) {
            return true;
        }

        return $user->isCurrentMemberOf($team)
            && (string) $merchandiseOrder->user_id === (string) $user->getKey();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MerchandiseOrder $merchandiseOrder): bool
    {
        return false;
    }

    public function delete(User $user, MerchandiseOrder $merchandiseOrder): bool
    {
        return false;
    }

    public function cancel(User $user, MerchandiseOrder $merchandiseOrder): bool
    {
        return $this->view($user, $merchandiseOrder)
            && (string) $merchandiseOrder->user_id === (string) $user->getKey()
            && $merchandiseOrder->canBeCancelled();
    }

    public function discount(User $user, MerchandiseOrder $merchandiseOrder): bool
    {
        return $this->managesOrderTeam($user, $merchandiseOrder)
            && $merchandiseOrder->canBeReviewed();
    }

    public function approve(User $user, MerchandiseOrder $merchandiseOrder): bool
    {
        return $this->managesOrderTeam($user, $merchandiseOrder)
            && $merchandiseOrder->canBeReviewed();
    }

    public function reject(User $user, MerchandiseOrder $merchandiseOrder): bool
    {
        return $this->managesOrderTeam($user, $merchandiseOrder)
            && $merchandiseOrder->canBeReviewed();
    }

    private function managesOrderTeam(User $user, MerchandiseOrder $merchandiseOrder): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $merchandiseOrder->loadMissing('team');
        $team = $merchandiseOrder->team;

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Shop)
            && $user->manages($team);
    }

    private function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
