<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\ActivityQualificationRequirement;
use App\Models\Booking;
use App\Models\Key;
use App\Models\KeyRing;
use App\Models\Qualification;
use App\Models\User;

class BookingPeople
{
    /**
     * @param  iterable<int, User>  $users
     * @return array<string, 'holder'|'other'>
     */
    public static function keyBadges(Booking $booking, iterable $users): array
    {
        $people = collect($users)->filter(fn (mixed $user): bool => $user instanceof User)->values();
        $requiredIds = $booking->requiredKeys()->modelKeys();

        if ($requiredIds === [] || $people->isEmpty()) {
            return [];
        }

        $rings = KeyRing::query()
            ->whereIn('holder_id', $people->map->getKey()->all())
            ->with('keys.covers')
            ->get()
            ->groupBy('holder_id');

        $badges = [];

        foreach ($people as $person) {
            $held = $rings->get($person->getKey(), collect());
            $useful = false;
            $any = false;

            foreach ($held as $ring) {
                foreach ($ring->keys as $key) {
                    $any = true;

                    if (self::opensRequired($key, $requiredIds)) {
                        $useful = true;
                    }
                }
            }

            if ($useful) {
                $badges[$person->getKey()] = 'holder';
            } elseif ($any) {
                $badges[$person->getKey()] = 'other';
            }
        }

        return $badges;
    }

    /**
     * @return list<array{name: string, keys: list<string>}>
     */
    public static function ringLines(User $person): array
    {
        return $person->keyRings()
            ->with('keys.covers')
            ->get()
            ->map(fn (KeyRing $ring): array => [
                'name' => $ring->name,
                'keys' => $ring->keyDescriptions(),
            ])
            ->all();
    }

    /**
     * @return array{panel: string, rings: list<array{name: string, keys: list<string>}>, relevant: list<array<string, mixed>>, other: list<array<string, mixed>>}
     */
    public static function panel(Booking $booking, User $person, string $panel): array
    {
        $booking->loadMissing('activity.qualificationRequirements');
        $groups = self::qualifications($person, $booking->activity, $booking);

        return [
            'panel' => $panel,
            'rings' => self::ringLines($person),
            'relevant' => $groups['relevant'],
            'other' => $groups['other'],
        ];
    }

    /**
     * @return array{relevant: list<array<string, mixed>>, other: list<array<string, mixed>>}
     */
    public static function qualifications(User $person, ?Activity $activity, ?Booking $booking = null): array
    {
        $requirements = $activity?->relationLoaded('qualificationRequirements')
            ? $activity->qualificationRequirements
            : ($activity?->qualificationRequirements()->get() ?? collect());

        $asOf = $booking?->qualificationAsOf();
        $relevant = [];
        $inDate = [];
        $expired = [];

        $person->loadMissing('qualifications');

        foreach ($person->qualifications as $qualification) {
            $line = self::qualificationLine($qualification);
            $matches = $requirements->contains(
                fn (ActivityQualificationRequirement $requirement): bool => $requirement->matches($qualification, $asOf),
            );

            if ($matches) {
                $relevant[] = $line;
            } elseif ($qualification->isExpired($asOf)) {
                $expired[] = $line;
            } else {
                $inDate[] = $line;
            }
        }

        return [
            'relevant' => $relevant,
            'other' => [...$inDate, ...$expired],
        ];
    }

    /**
     * @param  list<int|string>  $requiredIds
     */
    private static function opensRequired(Key $key, array $requiredIds): bool
    {
        if (in_array($key->getKey(), $requiredIds, true)) {
            return true;
        }

        return $key->covers->contains(
            fn (Key $covered): bool => in_array($covered->getKey(), $requiredIds, true),
        );
    }

    /**
     * @return array{id: string, type: string, summary: string, expires: ?string, expires_class: string, fields: list<array{label: string, value: string}>}
     */
    private static function qualificationLine(Qualification $qualification): array
    {
        $expires = $qualification->expires_on?->toFormattedDateString();

        return [
            'id' => $qualification->getKey(),
            'type' => $qualification->type_label,
            'summary' => $qualification->summary,
            'expires' => $expires,
            'expires_class' => match ($qualification->expiryColor()) {
                Palette::positive() => 'text-success-600 dark:text-success-400',
                Palette::uncertain() => 'text-warning-600 dark:text-warning-400',
                Palette::destructive() => 'text-danger-600 dark:text-danger-400',
                default => 'text-gray-500 dark:text-gray-400',
            },
            'fields' => self::fields($qualification),
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private static function fields(Qualification $qualification): array
    {
        return $qualification->detailFields();
    }
}
