<?php

namespace App\Support;

use App\Models\InboxNotification;
use App\Notifications\InboundReviewNotification;

final class ResolveInboundReviewInbox
{
    public function __invoke(): void
    {
        InboxNotification::query()
            ->whereNull('read_at')
            ->where('type', InboundReviewNotification::class)
            ->orderBy('id')
            ->each(fn (InboxNotification $notification) => $notification->markReadForDelivery());
    }
}
