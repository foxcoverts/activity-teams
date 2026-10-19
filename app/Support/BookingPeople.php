<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\ActivityQualificationRequirement;
use App\Models\Booking;
use App\Models\GirlguidingQualification;
use App\Models\Key;
use App\Models\KeyRing;
use App\Models\MountainTrainingQualification;
use App\Models\Qualification;
use App\Models\ScoutPermit;
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
        $groups = self::qualifications($person, $booking->activity);

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
    public static function qualifications(User $person, ?Activity $activity): array
    {
        $requirements = $activity?->relationLoaded('qualificationRequirements')
            ? $activity->qualificationRequirements
            : ($activity?->qualificationRequirements()->get() ?? collect());

        $relevant = [];
        $inDate = [];
        $expired = [];

        foreach ($person->qualifications()->with('detail')->get() as $qualification) {
            $line = self::qualificationLine($qualification);
            $matches = $requirements->contains(
                fn (ActivityQualificationRequirement $requirement): bool => $requirement->matches($qualification),
            );

            if ($matches) {
                $relevant[] = $line;
            } elseif ($qualification->isExpired()) {
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
     * @return array{id: string, type: string, summary: string, expires: ?string, fields: list<array{label: string, value: string}>}
     */
    private static function qualificationLine(Qualification $qualification): array
    {
        $expires = $qualification->detail instanceof MountainTrainingQualification
            ? null
            : $qualification->expires_on?->toFormattedDateString();

        return [
            'id' => $qualification->getKey(),
            'type' => $qualification->type_label,
            'summary' => $qualification->summary,
            'expires' => $expires,
            'fields' => self::fields($qualification),
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private static function fields(Qualification $qualification): array
    {
        $detail = $qualification->detail;

        if ($detail instanceof ScoutPermit) {
            $fields = [
                ['label' => __('Activity'), 'value' => (string) $detail->activity?->getLabel()],
            ];

            if ($detail->category !== null) {
                $fields[] = ['label' => __('Category'), 'value' => (string) $detail->category->getLabel()];
            }

            $fields[] = ['label' => __('Permit type'), 'value' => (string) $detail->permit_type?->getLabel()];
            $fields[] = ['label' => __('Restrictions'), 'value' => filled($detail->restrictions) ? $detail->restrictions : '—'];

            return $fields;
        }

        if ($detail instanceof MountainTrainingQualification) {
            return [
                ['label' => __('Award'), 'value' => (string) $detail->award?->getLabel()],
            ];
        }

        if ($detail instanceof GirlguidingQualification) {
            return [
                ['label' => __('Scheme'), 'value' => (string) $detail->scheme?->getLabel()],
                ['label' => __('Level'), 'value' => (string) $detail->level?->getLabel()],
            ];
        }

        return [];
    }
}
