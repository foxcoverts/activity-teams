<?php

namespace App\Filament\Auth;

use App\Enums\MembershipRole;
use App\Models\Team;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class Register extends BaseRegister
{
    public function mount(): void
    {
        abort_unless(User::query()->count() === 0, Response::HTTP_FORBIDDEN, __('This site is invite only.'));

        parent::mount();
    }

    protected function handleRegistration(array $data): Model
    {
        /** @var User $user */
        $user = parent::handleRegistration($data);
        $user->is_system_admin = true;
        $user->save();

        $team = Team::query()->create([
            'name' => __('Team'),
        ]);
        $team->grant($user, MembershipRole::Manager);

        return $user;
    }
}
