<?php

namespace App\Actions;

use App\Enums\MerchandiseOrderStatus;
use App\Models\MerchandiseOrder;
use App\Models\User;
use App\Support\ResolveMerchandiseOrderSubmittedInbox;
use Illuminate\Validation\ValidationException;

class CancelMerchandiseOrderAction
{
    public function __invoke(MerchandiseOrder $order, User $user): MerchandiseOrder
    {
        if ((string) $order->user_id !== (string) $user->getKey()) {
            throw ValidationException::withMessages([
                'order' => __('You can only cancel your own orders.'),
            ]);
        }

        if (! $order->canBeCancelled()) {
            throw ValidationException::withMessages([
                'order' => __('This order can no longer be cancelled.'),
            ]);
        }

        $order->update([
            'status' => MerchandiseOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $order = $order->refresh();

        app(ResolveMerchandiseOrderSubmittedInbox::class)($order);

        return $order;
    }
}
