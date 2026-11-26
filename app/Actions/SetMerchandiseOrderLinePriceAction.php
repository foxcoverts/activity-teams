<?php

namespace App\Actions;

use App\Models\MerchandiseOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SetMerchandiseOrderLinePriceAction
{
    public function __invoke(MerchandiseOrderLine $line, User $user, string|float $approvedUnitPrice): MerchandiseOrderLine
    {
        $line->loadMissing('order.team');
        $order = $line->order;

        if ($order === null || ! $user->can('discount', $order)) {
            throw ValidationException::withMessages([
                'approved_unit_price' => __('You cannot change prices on this order.'),
            ]);
        }

        $listPrice = $line->listUnitPrice();

        $validated = Validator::make(
            ['approved_unit_price' => $approvedUnitPrice],
            [
                'approved_unit_price' => [
                    'required',
                    'numeric',
                    'min:0',
                    'decimal:0,2',
                    'lte:'.$listPrice,
                ],
            ],
            [
                'approved_unit_price.lte' => __('The approved price cannot be higher than the list price.'),
            ],
        )->validate();

        $line->update([
            'approved_unit_price' => number_format((float) $validated['approved_unit_price'], 2, '.', ''),
        ]);

        $this->clampOrderDiscount($line);

        return $line->refresh();
    }

    private function clampOrderDiscount(MerchandiseOrderLine $line): void
    {
        $order = $line->order?->load('lines');

        if ($order === null) {
            return;
        }

        $subtotal = $order->itemsSubtotal();

        if ((float) $order->order_discount > $subtotal) {
            $order->update([
                'order_discount' => number_format($subtotal, 2, '.', ''),
            ]);
        }
    }
}
