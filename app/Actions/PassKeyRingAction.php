<?php

namespace App\Actions;

use App\Models\KeyRing;
use App\Models\User;
use App\Notifications\KeyRingPassedFromNotification;
use App\Notifications\KeyRingPassedToNotification;
use App\Notifications\KeyRingReturnedToCupboardNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PassKeyRingAction
{
    public function __invoke(User $actor, KeyRing $ring, ?string $holderId): void
    {
        Gate::forUser($actor)->authorize('pass', $ring);

        $currentId = $ring->holder_id === null ? null : (string) $ring->holder_id;
        $nextId = $holderId === null ? null : (string) $holderId;

        if ($currentId === $nextId) {
            return;
        }

        if (! $ring->canBePassedTo($nextId)) {
            throw ValidationException::withMessages([
                'holder_id' => __('Choose a member who can hold this key ring.'),
            ]);
        }

        $previous = $ring->holder;
        $next = $nextId === null ? null : User::query()->find($nextId);

        if ($nextId !== null && ! $next instanceof User) {
            throw ValidationException::withMessages([
                'holder_id' => __('Choose a member who can hold this key ring.'),
            ]);
        }

        $ring->forceFill(['holder_id' => $nextId])->save();
        $ring->unsetRelation('holder');

        $this->notify($actor, $ring, $previous instanceof User ? $previous : null, $next);
    }

    private function notify(User $actor, KeyRing $ring, ?User $previous, ?User $next): void
    {
        $skipIds = [(string) $actor->getKey()];

        if ($this->shouldNotify($next) && $next instanceof User && ! in_array((string) $next->getKey(), $skipIds, true)) {
            $next->notify(new KeyRingPassedToNotification($ring, $previous));
        }

        if ($this->shouldNotify($previous) && $previous instanceof User && ! in_array((string) $previous->getKey(), $skipIds, true)) {
            $previous->notify(new KeyRingPassedFromNotification($ring, $next));
            $skipIds[] = (string) $previous->getKey();
        }

        if ($next instanceof User) {
            return;
        }

        foreach ($this->interestedManagers($ring) as $manager) {
            if (in_array((string) $manager->getKey(), $skipIds, true)) {
                continue;
            }

            $manager->notify(new KeyRingReturnedToCupboardNotification($ring, $previous));
        }
    }

    private function shouldNotify(?User $user): bool
    {
        return $user instanceof User
            && ! $user->trashed()
            && ! $user->isPlaceholder()
            && ! $user->isSuspended()
            && $user->isActive()
            && $user->hasVerifiedEmail();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function interestedManagers(KeyRing $ring): EloquentCollection
    {
        $teamIds = $ring->interestedTeams()->modelKeys();

        if ($teamIds === []) {
            return new EloquentCollection;
        }

        return User::query()
            ->where('suspended', false)
            ->whereNotNull('password')
            ->whereNotNull('email_verified_at')
            ->whereHas('managedTeams', fn (Builder $teams) => $teams->whereKey($teamIds))
            ->get();
    }
}
