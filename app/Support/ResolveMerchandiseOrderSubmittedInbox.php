<?php

namespace App\Support;

use App\Models\InboxNotification;
use App\Models\MerchandiseOrder;
use App\Notifications\MerchandiseOrderSubmittedNotification;

final class ResolveMerchandiseOrderSubmittedInbox
{
    public function __invoke(MerchandiseOrder $order): void
    {
        InboxNotification::query()
            ->whereNull('read_at')
            ->where('type', MerchandiseOrderSubmittedNotification::class)
            ->where('data->subject_type', MerchandiseOrder::class)
            ->where('data->subject_id', $order->getKey())
            ->orderBy('id')
            ->each(fn (InboxNotification $notification) => $notification->markReadForDelivery());
    }
}
