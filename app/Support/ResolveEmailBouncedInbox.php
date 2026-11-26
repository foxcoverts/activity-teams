<?php

namespace App\Support;

use App\Models\InboxNotification;
use App\Models\User;
use App\Notifications\EmailBouncedNotification;

final class ResolveEmailBouncedInbox
{
    public function __invoke(User $member): void
    {
        InboxNotification::query()
            ->whereNull('read_at')
            ->where('type', EmailBouncedNotification::class)
            ->where('data->rebuild->member_id', $member->getKey())
            ->orderBy('id')
            ->each(fn (InboxNotification $notification) => $notification->markReadForDelivery());
    }
}
