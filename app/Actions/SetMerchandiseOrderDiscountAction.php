<?php

namespace App\Actions;

use App\Models\MerchandiseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SetMerchandiseOrderDiscountAction
{
    public function __invoke(MerchandiseOrder $order, User $user, string|float $orderDiscount): MerchandiseOrder
    {
        $order->loadMissing(['team', 'lines']);

        if (! $user->can('discount', $order)) {
            throw ValidationException::withMessages([
                'order_discount' => __('You cannot change the discount on this order.'),
            ]);
        }

        $subtotal = $order->itemsSubtotal();

        $validated = Validator::make(
            ['order_discount' => $orderDiscount],
            [
                'order_discount' => [
                    'required',
                    'numeric',
                    'min:0',
                    'decimal:0,2',
                    'lte:'.$subtotal,
                ],
            ],
            [
                'order_discount.lte' => __('The order discount cannot be more than the items subtotal.'),
            ],
        )->validate();

        $order->update([
            'order_discount' => number_format((float) $validated['order_discount'], 2, '.', ''),
        ]);

        return $order->refresh();
    }
}
