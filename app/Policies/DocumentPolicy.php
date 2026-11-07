<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        if (Filament::getCurrentPanel()?->getId() !== 'teams') {
            return true;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Team
            && ($user->isCurrentMemberOf($tenant) || $user->isSystemAdmin());
    }

    public function view(User $user, Document $document): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        $document->loadMissing('team');

        if ($user->isSystemAdmin()) {
            return $document->team instanceof Team;
        }

        return $document->team instanceof Team && $user->isCurrentMemberOf($document->team);
    }

    public function create(User $user): bool
    {
        return $this->managesCurrentTeam($user);
    }

    public function update(User $user, Document $document): bool
    {
        $document->loadMissing('team');

        return $document->team instanceof Team && $user->manages($document->team);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function restore(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
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
