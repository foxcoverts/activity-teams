<?php

namespace App\Listeners;

use App\Events\LeadInstructorCleared;
use App\Models\Team;
use App\Models\User;
use App\Notifications\LeadNeededNotification;

class SendLeadNeededEmail
{
    public function handle(LeadInstructorCleared $event): void
    {
        $event->booking->loadMissing(['team', 'attendees']);
        $team = $event->booking->team;

        if (! $team instanceof Team) {
            return;
        }

        $skipIds = $this->skipUserIds($event);

        $managers = $team->managers()
            ->where('users.suspended', false)
            ->whereNotNull('users.password')
            ->whereNotNull('users.email_verified_at')
            ->get();

        foreach ($managers as $manager) {
            if (in_array((string) $manager->getKey(), $skipIds, true)) {
                continue;
            }

            $manager->notify(new LeadNeededNotification($event->booking, $event->previousLead));
        }
    }

    /**
     * @return list<string>
     */
    private function skipUserIds(LeadInstructorCleared $event): array
    {
        $skipIds = [];

        if ($event->actor instanceof User) {
            $skipIds[] = (string) $event->actor->getKey();
        }

        if (! $event->attendeesWillBeUpdated) {
            return $skipIds;
        }

        foreach ($event->booking->attendees as $attendee) {
            if ($attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
                continue;
            }

            $skipIds[] = (string) $attendee->getKey();
        }

        return array_values(array_unique($skipIds));
    }
}
