<?php

namespace App\Policies;

use App\Enums\TeamFeature;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        if (Filament::getCurrentPanel()?->getId() === 'teams') {
            $team = $this->currentTeam();

            return $team instanceof Team
                && $team->featureEnabled(TeamFeature::Tasks)
                && ($user->isCurrentMemberOf($team) || $user->isSystemAdmin());
        }

        if ($user->isSystemAdmin()) {
            return Team::query()->where('features->tasks', true)->exists();
        }

        return $user->teams()->where('features->tasks', true)->exists();
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $task->loadMissing('team');

        if (! $task->team instanceof Team || ! $task->team->featureEnabled(TeamFeature::Tasks)) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        return $user->isCurrentMemberOf($task->team);
    }

    public function create(User $user): bool
    {
        return $this->managesTasksEnabledTeam($user);
    }

    public function update(User $user, Task $task): bool
    {
        $task->loadMissing('team');

        return $task->team instanceof Team
            && $task->team->featureEnabled(TeamFeature::Tasks)
            && $user->manages($task->team);
    }

    public function complete(User $user, Task $task): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $task->loadMissing('team');

        if (! $task->team instanceof Team || ! $task->team->featureEnabled(TeamFeature::Tasks)) {
            return false;
        }

        if ($user->isSystemAdmin()) {
            return true;
        }

        return $user->isCurrentMemberOf($task->team);
    }

    public function attachBooking(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->isCancelled() && $this->update($user, $task);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    private function managesTasksEnabledTeam(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $team = $this->currentTeam();

        return $team instanceof Team
            && $team->featureEnabled(TeamFeature::Tasks)
            && $user->manages($team);
    }

    private function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
