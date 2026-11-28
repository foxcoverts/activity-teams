<?php

namespace App\Actions;

use App\Models\MerchandiseOrderLine;
use App\Models\MerchandiseSupplierOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ToggleMerchandiseOrderLineArrivedAction
{
    public function __invoke(MerchandiseOrderLine $line, User $user, ?bool $arrived = null): MerchandiseOrderLine
    {
        $line->loadMissing(['supplierOrder.team', 'variant']);

        $supplierOrder = $line->supplierOrder;

        if (! $supplierOrder instanceof MerchandiseSupplierOrder || ! $supplierOrder->isSent()) {
            throw ValidationException::withMessages([
                'line' => __('Goods in is only available on sent supplier orders.'),
            ]);
        }

        if (! $user->can('recordGoodsIn', $supplierOrder)) {
            throw ValidationException::withMessages([
                'line' => __('You cannot record goods in for this supplier order.'),
            ]);
        }

        $shouldArrive = $arrived ?? ! $line->isArrived();

        $line->update([
            'arrived_at' => $shouldArrive ? now() : null,
        ]);

        return $line->fresh() ?? $line;
    }
}
