<?php

namespace App\Actions;

use App\Models\Key;
use App\Models\Lock;
use App\Models\Team;
use App\Models\User;
use App\Notifications\KeyHolderLeavingNotification;
use App\Notifications\LastManagerLeavingNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
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

        $managers = $this->managersForKeys($user, $keys);

        if ($keys->isNotEmpty() && $managers->isNotEmpty()) {
            Notification::send($managers, new KeyHolderLeavingNotification($user, $keys));
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
     * @return Collection<int, User>
     */
    private function managersForKeys(User $user, EloquentCollection $keys): Collection
    {
        $keyIds = $keys->flatMap(fn (Key $ring) => $ring->locks->modelKeys())->unique()->all();

        $teams = Team::query()
            ->with('managers')
            ->get()
            ->filter(function (Team $team) use ($keyIds): bool {
                if ($keyIds === []) {
                    return false;
                }

                return Lock::query()->relevantTo($team)->whereKey($keyIds)->exists();
            });

        if ($teams->isEmpty()) {
            $teams = $user->teams()->with('managers')->get();
        }

        return $teams
            ->flatMap(fn (Team $team) => $team->managers)
            ->unique('id')
            ->filter(fn (User $manager): bool => $manager->isActive() && ! $manager->isSuspended() && $manager->hasVerifiedEmail())
            ->values();
    }
}
