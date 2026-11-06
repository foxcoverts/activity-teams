<?php

namespace App\Policies;

use App\Models\NewsPost;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class NewsPostPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $tenant = Filament::getTenant();

        return ! $tenant || $user->isCurrentMemberOf($tenant);
    }

    public function view(User $user, NewsPost $newsPost): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        if ($newsPost->isLive() && $user->isCurrentMemberOf($newsPost->team)) {
            return true;
        }

        return $user->manages($newsPost->team);
    }

    public function create(User $user): bool
    {
        return $this->managesCurrentTeam($user);
    }

    public function update(User $user, NewsPost $newsPost): bool
    {
        $newsPost->loadMissing('team');

        return $newsPost->team instanceof Team && $user->manages($newsPost->team);
    }

    public function delete(User $user, NewsPost $newsPost): bool
    {
        return $this->update($user, $newsPost);
    }

    public function restore(User $user, NewsPost $newsPost): bool
    {
        return $this->update($user, $newsPost);
    }

    public function forceDelete(User $user, NewsPost $newsPost): bool
    {
        return $this->update($user, $newsPost);
    }

    private function managesCurrentTeam(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Team && $user->manages($tenant);
    }
}
