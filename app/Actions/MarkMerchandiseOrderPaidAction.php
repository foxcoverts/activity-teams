<?php

namespace App\Actions;

use App\Models\MerchandiseOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MarkMerchandiseOrderPaidAction
{
    public function __invoke(MerchandiseOrder $order, User $user): MerchandiseOrder
    {
        $order->loadMissing('team');

        if (! $user->can('markPaid', $order)) {
            throw ValidationException::withMessages([
                'order' => __('You cannot mark this order as paid.'),
            ]);
        }

        if (! $order->isUnpaid()) {
            throw ValidationException::withMessages([
                'order' => __('This order is not unpaid.'),
            ]);
        }

        $order->update([
            'paid_at' => now(),
        ]);

        return $order->refresh();
    }
}
