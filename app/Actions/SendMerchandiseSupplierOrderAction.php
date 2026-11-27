<?php

namespace App\Actions;

use App\Models\MerchandiseSupplierOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendMerchandiseSupplierOrderAction
{
    public function __invoke(MerchandiseSupplierOrder $supplierOrder, User $user): MerchandiseSupplierOrder
    {
        $supplierOrder->loadMissing(['team', 'supplier', 'lines']);

        if (! $user->can('send', $supplierOrder)) {
            throw ValidationException::withMessages([
                'supplier_order' => __('You cannot send this supplier order.'),
            ]);
        }

        if (! $supplierOrder->isDraft()) {
            throw ValidationException::withMessages([
                'supplier_order' => __('This supplier order has already been sent.'),
            ]);
        }

        if ($supplierOrder->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'supplier_order' => __('Add at least one line before sending.'),
            ]);
        }

        $wrongSupplier = $supplierOrder->lines->contains(
            function ($line) use ($supplierOrder): bool {
                $line->loadMissing('item');

                return $line->item?->merchandise_supplier_id !== $supplierOrder->merchandise_supplier_id;
            },
        );

        if ($wrongSupplier) {
            throw ValidationException::withMessages([
                'supplier_order' => __('Every line must belong to this supplier.'),
            ]);
        }

        return DB::transaction(function () use ($supplierOrder): MerchandiseSupplierOrder {
            $supplierOrder->update([
                'sent_at' => now(),
                'open_draft_key' => null,
            ]);

            return $supplierOrder->fresh(['lines', 'supplier', 'team']) ?? $supplierOrder;
        });
    }
}
