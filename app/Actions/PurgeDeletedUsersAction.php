<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Carbon;

class PurgeDeletedUsersAction
{
    /**
     * Personal data stripped from a deleted account after 30 days.
     *
     * @var list<string>
     */
    public const ATTRIBUTES = [
        'email',
        'email_verified_at',
        'phone',
        'emergency_name',
        'emergency_phone',
        'password',
        'remember_token',
        'ical_token',
        'timezone',
        'oversight',
        'is_system_admin',
        'suspended',
        'privacy_notice_accepted_at',
        'profile_confirmed_at',
    ];

    public function purgeDue(?Carbon $now = null): int
    {
        $cutoff = ($now ?? now())->subDays(30);
        $purged = 0;

        User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->whereNotNull('email')
            ->each(function (User $user) use (&$purged): void {
                $this->purge($user);
                $purged++;
            });

        return $purged;
    }

    public function purge(User $user): void
    {
        $values = [];

        foreach (self::ATTRIBUTES as $attribute) {
            $values[$attribute] = match ($attribute) {
                'oversight', 'is_system_admin', 'suspended' => false,
                default => null,
            };
        }

        $user->forceFill($values)->save();
    }
}
