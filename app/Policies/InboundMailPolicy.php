<?php

namespace App\Policies;

use App\Models\InboundMail;
use App\Models\User;

class InboundMailPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function view(User $user, InboundMail $inboundMail): bool
    {
        return $user->isSystemAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InboundMail $inboundMail): bool
    {
        return $user->isSystemAdmin();
    }

    public function delete(User $user, InboundMail $inboundMail): bool
    {
        return $user->isSystemAdmin();
    }
}
