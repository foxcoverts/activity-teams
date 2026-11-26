<?php

namespace App\Support;

use App\Models\InboxNotification;
use App\Models\Team;
use App\Notifications\LastManagerLeavingNotification;

final class ResolveLastManagerLeavingInbox
{
    public function __invoke(?Team $team = null): void
    {
        InboxNotification::query()
            ->whereNull('read_at')
            ->where('type', LastManagerLeavingNotification::class)
            ->orderBy('id')
            ->get()
            ->each(function (InboxNotification $notification) use ($team): void {
                $teamIds = $notification->data['rebuild']['team_ids'] ?? [];

                if (! is_array($teamIds) || $teamIds === []) {
                    $notification->markReadForDelivery();

                    return;
                }

                if ($team instanceof Team && ! in_array($team->getKey(), $teamIds, true)) {
                    return;
                }

                $stillWithoutManager = Team::query()
                    ->whereKey($teamIds)
                    ->whereDoesntHave('managers')
                    ->exists();

                if (! $stillWithoutManager) {
                    $notification->markReadForDelivery();
                }
            });
    }
}
