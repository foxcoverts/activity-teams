<?php

namespace App\Filament\Support;

use App\Models\User;
use App\Support\Avatars;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

/**
 * Shared User resolution for person Column / Entry (and PersonStacked / PersonSelect).
 */
final class Person
{
    public static function resolve(mixed $state, ?Model $record = null, ?Model $relatedRecord = null): ?User
    {
        if ($state instanceof User) {
            return $state;
        }

        if ($relatedRecord instanceof User) {
            return $relatedRecord;
        }

        if ($record instanceof User) {
            return $record;
        }

        return null;
    }

    public static function resolveId(mixed $id): ?User
    {
        if ($id instanceof User) {
            return $id;
        }

        if (blank($id)) {
            return null;
        }

        return User::query()->find($id);
    }

    public static function optionLabel(User $person): string
    {
        // `flex` (not inline-flex): Filament wraps HTML labels in a block
        // span; inline-flex baseline alignment leaves a few pixels below the avatar.
        return '<span class="flex items-center gap-2">'
            .view('filament.bookings.person-identity', [
                'person' => [
                    'name' => $person->name,
                    'avatar_url' => Avatars::userUrl($person, 64),
                ],
            ])->render()
            .'</span>';
    }

    /**
     * @param  iterable<mixed>  $users
     * @return array<string, string>
     */
    public static function optionsFor(iterable $users): array
    {
        if ($users instanceof Enumerable) {
            $users = $users->all();
        }

        return Collection::make($users)
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->mapWithKeys(fn (User $user): array => [
                (string) $user->getKey() => self::optionLabel($user),
            ])
            ->all();
    }
}
