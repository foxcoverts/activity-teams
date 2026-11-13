<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Enums\PastBookingAttendeeStatus;
use App\Models\Booking;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class BookingGuestList
{
    /**
     * @param  iterable<int, User>  $attendees
     * @param  list<BookingAttendeeStatus>  $visibleStatuses
     * @return array{
     *     groups: list<array{key: string, label: string, open: bool, people: list<array{id: string, name: string, avatar_url: string, status: BookingAttendeeStatus|PastBookingAttendeeStatus, guest: bool, under_18: bool, is_parent: bool, qualified: bool, key_badge: ?string}>}>,
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

        $booking->loadMissing('activity.activityAudiences.qualificationRequirements');

        $people = collect($attendees)
            ->filter(fn (mixed $attendee): bool => $attendee instanceof User)
            ->filter(fn (User $attendee): bool => $includeSuspended || ! $attendee->isSuspended())
            ->values();

        $requiresQualification = $booking->requiresQualification();

        if ($requiresQualification && $people->isNotEmpty()) {
            (new EloquentCollection($people->all()))->loadMissing('qualifications');
        }

        $memberRoles = $booking->team === null || $people->isEmpty()
            ? collect()
            : TeamMembership::query()
                ->where('team_id', $booking->team->getKey())
                ->whereNull('left_at')
                ->whereIn('user_id', $people->map->getKey()->all())
                ->get(['user_id', 'role'])
                ->mapWithKeys(fn (TeamMembership $membership): array => [(string) $membership->user_id => $membership->role]);
        $keyBadges = $includeAccessDetails ? BookingPeople::keyBadges($booking, $people) : [];
        $showQualified = $includeAccessDetails && $requiresQualification;
        $showKeys = $includeAccessDetails && $booking->requiredLocks()->isNotEmpty();

        $rows = $people
            ->map(function (User $person) use ($booking, $keyBadges, $memberRoles, $requiresQualification): ?array {
                $status = $person->attendance?->status;

                if (! $status instanceof BookingAttendeeStatus && ! $status instanceof PastBookingAttendeeStatus) {
                    return null;
                }

                return [
                    'id' => (string) $person->getKey(),
                    'name' => $person->name,
                    'avatar_url' => Avatars::userUrl($person, 64),
                    'status' => $status,
                    'guest' => ! $memberRoles->has((string) $person->getKey()),
                    'under_18' => $person->isUnder18(),
                    'is_parent' => $memberRoles->get((string) $person->getKey()) === MembershipRole::Parent,
                    'qualified' => $requiresQualification && $booking->metBy($person),
                    'key_badge' => $keyBadges[$person->getKey()] ?? null,
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
            $groupStatus = $booking->isPast()
                ? PastBookingAttendeeStatus::from($status->value)
                : $status;

            $groupPeople = $rows
                ->reject(fn (array $person): bool => $person['id'] === (string) $booking->lead_instructor_id)
                ->filter(fn (array $person): bool => $person['status']->isSame($status))
                ->sort(fn (array $first, array $second): int => strnatcasecmp($first['name'], $second['name'])
                    ?: strcmp($first['id'], $second['id']))
                ->values()
                ->all();

            if ($groupPeople === []) {
                continue;
            }

            $groups[] = [
                'key' => $groupStatus->value,
                'label' => $groupStatus->getLabel(),
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
