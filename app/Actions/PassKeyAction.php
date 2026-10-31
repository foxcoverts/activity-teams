<?php

namespace App\Actions;

use App\Enums\MailChannel;
use App\Models\Key;
use App\Models\User;
use App\Notifications\KeyPassedFromNotification;
use App\Notifications\KeyPassedToNotification;
use App\Notifications\KeyReturnedToCupboardNotification;
use App\Support\MailPreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PassKeyAction
{
    public function __invoke(User $actor, Key $ring, ?string $holderId): void
    {
        Gate::forUser($actor)->authorize('pass', $ring);

        $currentId = $ring->holder_id === null ? null : (string) $ring->holder_id;
        $nextId = $holderId === null ? null : (string) $holderId;

        if ($currentId === $nextId) {
            return;
        }

        if (! $ring->canBePassedTo($nextId)) {
            throw ValidationException::withMessages([
                'holder_id' => __('Choose a member who can hold this key.'),
            ]);
        }

        $previous = $ring->holder;
        $next = $nextId === null ? null : User::query()->find($nextId);

        if ($nextId !== null && ! $next instanceof User) {
            throw ValidationException::withMessages([
                'holder_id' => __('Choose a member who can hold this key.'),
            ]);
        }

        $ring->forceFill(['holder_id' => $nextId])->save();
        $ring->unsetRelation('holder');

        $this->notify($actor, $ring, $previous instanceof User ? $previous : null, $next);
    }

    private function notify(User $actor, Key $ring, ?User $previous, ?User $next): void
    {
        $skipIds = [(string) $actor->getKey()];

        if ($this->shouldNotify($next) && $next instanceof User && ! in_array((string) $next->getKey(), $skipIds, true)
            && MailPreference::allows($next, MailChannel::KeysMail)) {
            $next->notify(new KeyPassedToNotification($ring, $previous));
        }

        if ($this->shouldNotify($previous) && $previous instanceof User && ! in_array((string) $previous->getKey(), $skipIds, true)
            && MailPreference::allows($previous, MailChannel::KeysMail)) {
            $previous->notify(new KeyPassedFromNotification($ring, $next));
            $skipIds[] = (string) $previous->getKey();
        }

        if ($next instanceof User) {
            return;
        }

        foreach ($this->interestedManagers($ring) as $manager) {
            if (in_array((string) $manager->getKey(), $skipIds, true)) {
                continue;
            }

            $manager->notify(new KeyReturnedToCupboardNotification($ring, $previous));
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
    private function interestedManagers(Key $ring): EloquentCollection
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
