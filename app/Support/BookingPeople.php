<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Enums\KeyHolderAttendance;
use App\Enums\KeySituation;
use App\Enums\PastBookingAttendeeStatus;
use App\Models\ActivityQualificationRequirement;
use App\Models\Booking;
use App\Models\Key;
use App\Models\Lock;
use App\Models\Qualification;
use App\Models\User;
use Illuminate\Support\Collection;

class BookingPeople
{
    /**
     * @param  iterable<int, User>  $users
     * @return array<string, 'holder'|'other'>
     */
    public static function keyBadges(Booking $booking, iterable $users): array
    {
        $people = collect($users)->filter(fn (mixed $user): bool => $user instanceof User)->values();
        $requiredIds = $booking->requiredLocks()->modelKeys();

        if ($requiredIds === [] || $people->isEmpty()) {
            return [];
        }

        $rings = Key::query()
            ->whereIn('holder_id', $people->map->getKey()->all())
            ->with('locks.covers')
            ->get()
            ->groupBy('holder_id');

        $badges = [];

        foreach ($people as $person) {
            $held = $rings->get($person->getKey(), collect());
            $useful = false;
            $any = false;

            foreach ($held as $ring) {
                foreach ($ring->locks as $key) {
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

    public static function holderAttendance(Booking $booking): KeyHolderAttendance
    {
        $booking->loadMissing('attendees');

        $accepted = $booking->attendees->filter(
            fn (User $person): bool => $person->attendance?->isAccepted() ?? false,
        );

        return in_array('holder', self::keyBadges($booking, $accepted), true)
            ? KeyHolderAttendance::Attending
            : KeyHolderAttendance::Missing;
    }

    /**
     * Person-first key roster for the booking Keys modal: situation groups of holders
     * (rings merged per person) plus cupboard rings without a holder.
     *
     * @return list<array{
     *     situation: KeySituation,
     *     people: list<array{id: string, name: string, avatar_url: string, rings: list<array{name: string, locks: list<string>}>}>,
     *     rings: list<array{name: string, locks: list<string>}>
     * }>
     */
    public static function keyHolderGroups(Booking $booking): array
    {
        $booking->loadMissing('attendees');

        $statusById = $booking->attendees->mapWithKeys(
            fn (User $person): array => [(string) $person->getKey() => $person->attendance?->status],
        );

        /** @var array<string, array<string, array{id: string, name: string, avatar_url: string, rings: array<string, array{name: string, locks: array<string, string>}>}>> $people */
        $people = [];
        /** @var array<string, array{name: string, locks: array<string, string>}> $cupboard */
        $cupboard = [];

        foreach (KeySituation::displayOrder() as $situation) {
            if ($situation !== KeySituation::Cupboard) {
                $people[$situation->value] = [];
            }
        }

        foreach ($booking->requiredLocks() as $lock) {
            foreach ($lock->accessKeys()->with('holder')->get() as $ring) {
                $situation = self::situation($ring, $statusById);
                $ringId = (string) $ring->getKey();
                $lockName = $lock->name;

                if ($situation === KeySituation::Cupboard) {
                    $cupboard[$ringId] ??= [
                        'name' => $ring->name,
                        'locks' => [],
                    ];
                    $cupboard[$ringId]['locks'][$lockName] = $lockName;

                    continue;
                }

                $holder = $ring->holder;
                if (! $holder instanceof User) {
                    continue;
                }

                $holderId = (string) $holder->getKey();
                $people[$situation->value][$holderId] ??= [
                    'id' => $holderId,
                    'name' => $holder->name,
                    'avatar_url' => Avatars::userUrl($holder, 64),
                    'rings' => [],
                ];
                $people[$situation->value][$holderId]['rings'][$ringId] ??= [
                    'name' => $ring->name,
                    'locks' => [],
                ];
                $people[$situation->value][$holderId]['rings'][$ringId]['locks'][$lockName] = $lockName;
            }
        }

        $groups = [];

        foreach (KeySituation::displayOrder() as $situation) {
            if ($situation === KeySituation::Cupboard) {
                if ($cupboard === []) {
                    continue;
                }

                $groups[] = [
                    'situation' => $situation,
                    'people' => [],
                    'rings' => collect($cupboard)
                        ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                        ->map(fn (array $ring): array => [
                            'name' => $ring['name'],
                            'locks' => array_values($ring['locks']),
                        ])
                        ->values()
                        ->all(),
                ];

                continue;
            }

            $holders = $people[$situation->value] ?? [];
            if ($holders === []) {
                continue;
            }

            $groups[] = [
                'situation' => $situation,
                'people' => collect($holders)
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->map(fn (array $person): array => [
                        'id' => $person['id'],
                        'name' => $person['name'],
                        'avatar_url' => $person['avatar_url'],
                        'rings' => collect($person['rings'])
                            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                            ->map(fn (array $ring): array => [
                                'name' => $ring['name'],
                                'locks' => array_values($ring['locks']),
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
                'rings' => [],
            ];
        }

        return $groups;
    }

    /**
     * @param  Collection<string, BookingAttendeeStatus|PastBookingAttendeeStatus|null>  $statusById
     */
    private static function situation(Key $ring, Collection $statusById): KeySituation
    {
        if ($ring->holder_id === null) {
            return KeySituation::Cupboard;
        }

        $status = $statusById->get((string) $ring->holder_id);

        if (! $status instanceof BookingAttendeeStatus && ! $status instanceof PastBookingAttendeeStatus) {
            return KeySituation::NotInvited;
        }

        return match (true) {
            $status->isSame(BookingAttendeeStatus::Accepted) => KeySituation::Attending,
            $status->isSame(BookingAttendeeStatus::Declined) => KeySituation::CannotMakeIt,
            $status->isSame(BookingAttendeeStatus::Tentative),
            $status->isSame(BookingAttendeeStatus::NeedsAction) => KeySituation::Invited,
            default => KeySituation::NotInvited,
        };
    }

    /**
     * @return list<array{name: string, keys: list<string>}>
     */
    public static function ringLines(User $person): array
    {
        return $person->keys()
            ->with('locks.covers')
            ->get()
            ->map(fn (Key $ring): array => [
                'name' => $ring->name,
                'keys' => $ring->lockDescriptions(),
            ])
            ->all();
    }

    /**
     * @return array{panel: string, rings: list<array{name: string, keys: list<string>}>, relevant: list<array<string, mixed>>, other: list<array<string, mixed>>}
     */
    public static function panel(Booking $booking, User $person, string $panel): array
    {
        $booking->loadMissing('activity.activityAudiences.qualificationRequirements');
        $groups = self::qualifications($person, $booking);

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
    public static function qualifications(User $person, Booking $booking): array
    {
        $booking->loadMissing('activity.activityAudiences.qualificationRequirements');
        $requirements = $booking->activityAudience()?->qualificationRequirements ?? collect();

        $asOf = $booking->qualificationAsOf();
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
    private static function opensRequired(Lock $key, array $requiredIds): bool
    {
        if (in_array($key->getKey(), $requiredIds, true)) {
            return true;
        }

        return $key->covers->contains(
            fn (Lock $covered): bool => in_array($covered->getKey(), $requiredIds, true),
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
