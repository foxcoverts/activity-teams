<?php

namespace App\Policies;

use App\Models\Qualification;
use App\Models\User;

class QualificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->managedTeams()->exists() || $user->hasOversight() || $user->isSystemAdmin();
    }

    public function view(User $user, Qualification $qualification): bool
    {
        if ($qualification->user_id === $user->getKey()) {
            return true;
        }

        if ($user->hasOversight() || $user->isSystemAdmin()) {
            return true;
        }

        $member = $this->member($qualification);

        return $member instanceof User && Qualification::canMaintain($user, $member);
    }

    public function create(User $user): bool
    {
        return $user->managedTeams()->exists() || $user->isSystemAdmin();
    }

    public function update(User $user, Qualification $qualification): bool
    {
        $member = $this->member($qualification);

        return $member instanceof User && Qualification::canMaintain($user, $member);
    }

    public function delete(User $user, Qualification $qualification): bool
    {
        return $this->update($user, $qualification);
    }

    private function member(Qualification $qualification): ?User
    {
        if ($qualification->relationLoaded('user')) {
            $user = $qualification->user;

            return $user instanceof User ? $user : null;
        }

        $user = User::query()->find($qualification->user_id);

        return $user instanceof User ? $user : null;
    }
}
