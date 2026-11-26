<?php

namespace App\Support;

use App\Models\InboxNotification;
use App\Models\Key;
use App\Models\User;
use App\Notifications\KeyHolderLeavingNotification;

final class ResolveKeyHolderLeavingInbox
{
    public function __invoke(User $member): void
    {
        InboxNotification::query()
            ->whereNull('read_at')
            ->where('type', KeyHolderLeavingNotification::class)
            ->where('data->rebuild->member_id', $member->getKey())
            ->orderBy('id')
            ->get()
            ->each(function (InboxNotification $notification) use ($member): void {
                $keyIds = $notification->data['rebuild']['key_ids'] ?? [];

                if (! is_array($keyIds) || $keyIds === []) {
                    $notification->markReadForDelivery();

                    return;
                }

                $stillHeld = Key::query()
                    ->whereKey($keyIds)
                    ->where('holder_id', $member->getKey())
                    ->exists();

                if (! $stillHeld) {
                    $notification->markReadForDelivery();
                }
            });
    }
}
