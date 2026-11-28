<?php

namespace App\Policies;

use App\Enums\TeamFeature;
use App\Models\MerchandiseBasketLine;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class MerchandiseBasketLinePolicy
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

    public function view(User $user, MerchandiseBasketLine $merchandiseBasketLine): bool
    {
        return $this->ownsLine($user, $merchandiseBasketLine);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MerchandiseBasketLine $merchandiseBasketLine): bool
    {
        return $this->ownsLine($user, $merchandiseBasketLine);
    }

    public function delete(User $user, MerchandiseBasketLine $merchandiseBasketLine): bool
    {
        return $this->ownsLine($user, $merchandiseBasketLine);
    }

    private function ownsLine(User $user, MerchandiseBasketLine $line): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $line->loadMissing('team');
        $team = $line->team;

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Shop)
            && $user->isCurrentMemberOf($team)
            && (string) $line->user_id === (string) $user->getKey();
    }

    private function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
