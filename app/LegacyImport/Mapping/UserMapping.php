<?php

namespace App\LegacyImport\Mapping;

use App\Enums\MembershipRole;
use Illuminate\Database\Connection;

final class UserMapping
{
    /**
     * @param  object{
     *     role?: string|null,
     *     accreditations?: string|null,
     *     password?: string|null,
     *     section?: string|null
     * }  $legacyUser
     * @return array{
     *     suspended: bool,
     *     oversight: bool,
     *     is_system_admin: bool,
     *     membership_role: MembershipRole,
     *     under_18: bool,
     *     password: string|null
     * }
     */
    public static function attributes(object $legacyUser): array
    {
        $role = (string) ($legacyUser->role ?? 'guest');
        $accreditations = self::accreditations($legacyUser->accreditations ?? '');
        $runsBookings = $role === 'team-leader' || in_array('manage-bookings', $accreditations, true);
        $oversightOnly = in_array('manage-users', $accreditations, true) && ! $runsBookings;
        $password = $legacyUser->password ?? null;

        if ($password === '') {
            $password = null;
        }

        return [
            // Legacy "suspended" means left the climbing team, not AT account suspension.
            'suspended' => false,
            'oversight' => $oversightOnly,
            'is_system_admin' => $role === 'team-leader',
            'membership_role' => $runsBookings ? MembershipRole::Manager : MembershipRole::Member,
            'under_18' => false,
            'password' => $password,
        ];
    }

    /**
     * Suspended accounts with no accepted/tentative/declined attendance are
     * omitted. Qualifications alone do not keep them.
     */
    public static function shouldImport(object $legacyUser, Connection $legacy): bool
    {
        if ((string) ($legacyUser->role ?? '') !== 'suspended') {
            return true;
        }

        return $legacy->table('booking_user')
            ->where('user_id', $legacyUser->id)
            ->where('status', '!=', 'needs-action')
            ->exists();
    }

    /**
     * Legacy suspended accounts that are imported stay people but are not joined to Climbing.
     */
    public static function shouldJoinTeam(object $legacyUser): bool
    {
        return (string) ($legacyUser->role ?? '') !== 'suspended';
    }

    /**
     * @return list<string>
     */
    private static function accreditations(mixed $raw): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return array_values(array_filter(explode(',', $raw)));
    }
}
