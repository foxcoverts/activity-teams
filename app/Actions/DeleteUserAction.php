<?php

namespace App\Actions;

use App\Models\Key;
use App\Models\Team;
use App\Models\User;
use App\Notifications\KeyHolderLeavingNotification;
use App\Notifications\LastManagerLeavingNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class DeleteUserAction
{
    public function delete(User $actor, User $user, ?string $password = null): void
    {
        Gate::forUser($actor)->authorize('delete', $user);

        if ($actor->is($user) && ! Hash::check((string) $password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('The password is incorrect.'),
            ]);
        }

        if ($user->isLastSystemAdmin()) {
            throw ValidationException::withMessages([
                'password' => __('You are the last system administrator. Another administrator must exist before this account can be deleted.'),
            ]);
        }

        $teamsWithoutManager = $user->teamsLeftWithoutActiveManager();
        $keys = $user->keys()->with('locks')->get();

        $user->delete();

        if ($teamsWithoutManager->isNotEmpty()) {
            Notification::send($this->systemAdmins(), new LastManagerLeavingNotification($user, $teamsWithoutManager));
        }

        if ($keys->isNotEmpty()) {
            $this->notifyManagersOfHeldKeys($user, $keys);
        }
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function systemAdmins(): EloquentCollection
    {
        return User::query()
            ->where('is_system_admin', true)
            ->where('suspended', false)
            ->whereNotNull('password')
            ->whereNotNull('email_verified_at')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Key>  $keys
     */
    private function notifyManagersOfHeldKeys(User $user, EloquentCollection $keys): void
    {
        /** @var array<string, array{team: Team, keys: EloquentCollection<int, Key>}> $groups */
        $groups = [];
        $unmatched = new EloquentCollection;

        foreach ($keys as $ring) {
            $teams = $ring->interestedTeams();

            if ($teams->isEmpty()) {
                $unmatched->push($ring);

                continue;
            }

            foreach ($teams as $team) {
                $groups[(string) $team->getKey()]['team'] = $team;
                $groups[(string) $team->getKey()]['keys'] ??= new EloquentCollection;
                $groups[(string) $team->getKey()]['keys']->push($ring);
            }
        }

        if ($unmatched->isNotEmpty()) {
            foreach ($user->teams()->with('managers')->get() as $team) {
                $groups[(string) $team->getKey()]['team'] = $team;
                $groups[(string) $team->getKey()]['keys'] ??= new EloquentCollection;

                foreach ($unmatched as $ring) {
                    $groups[(string) $team->getKey()]['keys']->push($ring);
                }
            }
        }

        foreach ($groups as $group) {
            $team = $group['team'];
            $team->loadMissing('managers');

            $managers = $team->managers
                ->filter(fn (User $manager): bool => $manager->isActive() && ! $manager->isSuspended() && $manager->hasVerifiedEmail())
                ->values();

            if ($managers->isEmpty()) {
                continue;
            }

            Notification::send($managers, new KeyHolderLeavingNotification($user, $group['keys'], $team));
        }
    }
}
