<?php

namespace App\Policies;

use App\Enums\TeamFeature;
use App\Models\BookingDebrief;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class BookingDebriefPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $team = $this->currentTeam();

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Debrief)
            && ($user->isCurrentMemberOf($team) || $user->isSystemAdmin());
    }

    public function view(User $user, BookingDebrief $debrief): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $debrief->loadMissing('booking.team');
        $team = $debrief->booking?->team;

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Debrief)
            && ($user->isCurrentMemberOf($team) || $user->isSystemAdmin());
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, BookingDebrief $debrief): bool
    {
        return false;
    }

    public function delete(User $user, BookingDebrief $debrief): bool
    {
        return false;
    }

    private function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
