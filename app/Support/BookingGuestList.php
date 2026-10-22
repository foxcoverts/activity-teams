<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\User;

class BookingGuestList
{
    /**
     * @param  iterable<int, User>  $attendees
     * @param  list<BookingAttendeeStatus>  $visibleStatuses
     * @return array{
     *     groups: list<array{key: string, label: string, open: bool, people: list<array{id: string, name: string, status: BookingAttendeeStatus, guest: bool, qualified: bool, key_badge: ?string}>}>,
     *     show_qualified: bool,
     *     show_keys: bool
     * }
     */
    public static function forBooking(
        Booking $booking,
        iterable $attendees,
        array $visibleStatuses,
        bool $includeSuspended,
        bool $includeAccessDetails,
    ): array {
        $booking->loadMissing('team');

        if ($includeAccessDetails) {
            $booking->loadMissing('activity.qualificationRequirements');
        }

        $people = collect($attendees)
            ->filter(fn (mixed $attendee): bool => $attendee instanceof User)
            ->filter(fn (User $attendee): bool => $includeSuspended || ! $attendee->isSuspended())
            ->values();

        $currentMemberIds = $booking->team === null || $people->isEmpty()
            ? []
            : $booking->team->members()
                ->whereKey($people->map->getKey()->all())
                ->pluck('users.id')
                ->map(fn (int|string $id): string => (string) $id)
                ->all();

        $memberIdLookup = array_fill_keys($currentMemberIds, true);
        $keyBadges = $includeAccessDetails ? BookingPeople::keyBadges($booking, $people) : [];
        $activity = $includeAccessDetails ? $booking->activity : null;
        $showQualified = $activity instanceof Activity && $activity->requiresQualification();
        $showKeys = $includeAccessDetails && $booking->requiredKeys()->isNotEmpty();

        $rows = $people
            ->map(function (User $person) use ($activity, $includeAccessDetails, $keyBadges, $memberIdLookup, $showQualified): ?array {
                $status = $person->attendance?->status;

                if (! $status instanceof BookingAttendeeStatus) {
                    return null;
                }

                return [
                    'id' => (string) $person->getKey(),
                    'name' => $person->name,
                    'status' => $status,
                    'guest' => ! isset($memberIdLookup[(string) $person->getKey()]),
                    'qualified' => $includeAccessDetails
                        && $showQualified
                        && $activity instanceof Activity
                        && $activity->metBy($person),
                    'key_badge' => $includeAccessDetails
                        ? ($keyBadges[$person->getKey()] ?? null)
                        : null,
                ];
            })
            ->filter(fn (?array $person): bool => $person !== null)
            ->values();

        $groups = [];
        $lead = $rows->first(
            fn (array $person): bool => $person['id'] === (string) $booking->lead_instructor_id,
        );

        if ($lead !== null) {
            $groups[] = [
                'key' => 'lead-instructor',
                'label' => __('Lead Instructor'),
                'open' => true,
                'people' => [$lead],
            ];
        }

        foreach ($visibleStatuses as $status) {
            $groupPeople = $rows
                ->reject(fn (array $person): bool => $person['id'] === (string) $booking->lead_instructor_id)
                ->filter(fn (array $person): bool => $person['status'] === $status)
                ->sort(fn (array $first, array $second): int => strnatcasecmp($first['name'], $second['name'])
                    ?: strcmp($first['id'], $second['id']))
                ->values()
                ->all();

            if ($groupPeople === []) {
                continue;
            }

            $groups[] = [
                'key' => $status->value,
                'label' => $status->getLabel(),
                'open' => $status === BookingAttendeeStatus::Accepted,
                'people' => $groupPeople,
            ];
        }

        return [
            'groups' => $groups,
            'show_qualified' => $showQualified,
            'show_keys' => $showKeys,
        ];
    }
}
