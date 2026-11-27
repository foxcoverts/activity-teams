<?php

namespace App\Actions;

use App\Models\MerchandiseOrderLine;
use App\Models\MerchandiseSupplier;
use App\Models\MerchandiseSupplierOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncMerchandiseSupplierOrderDraftLinesAction
{
    public function __construct(
        private EnsureMerchandiseSupplierOrderDraftAction $ensureDraft,
    ) {}

    /**
     * @param  list<string>|Collection<int, string>  $selectedLineIds
     */
    public function __invoke(
        MerchandiseSupplierOrder $supplierOrder,
        User $user,
        array|Collection $selectedLineIds,
    ): MerchandiseSupplierOrder {
        $supplierOrder->loadMissing(['team', 'supplier']);

        if (! $user->can('update', $supplierOrder)) {
            throw ValidationException::withMessages([
                'supplier_order' => __('You cannot change this supplier order.'),
            ]);
        }

        if (! $supplierOrder->isDraft()) {
            throw ValidationException::withMessages([
                'supplier_order' => __('Sent supplier orders cannot be changed.'),
            ]);
        }

        $team = $supplierOrder->team;
        $supplier = $supplierOrder->supplier;
        $selectedIds = collect($selectedLineIds)->filter()->unique()->values();

        $candidates = MerchandiseOrderLine::query()
            ->boardCandidatesForSupplier($team, $supplier)
            ->with(['item', 'supplierOrder'])
            ->get()
            ->keyBy(fn (MerchandiseOrderLine $line): string => $line->getKey());

        foreach ($selectedIds as $lineId) {
            if (! $candidates->has($lineId)) {
                throw ValidationException::withMessages([
                    'lines' => __('One or more selected lines cannot be added to this supplier order.'),
                ]);
            }
        }

        return DB::transaction(function () use ($supplierOrder, $candidates, $selectedIds): MerchandiseSupplierOrder {
            foreach ($candidates as $line) {
                $shouldInclude = $selectedIds->contains($line->getKey());
                $isOnThisDraft = $line->merchandise_supplier_order_id === $supplierOrder->getKey();

                if ($shouldInclude && ! $isOnThisDraft) {
                    if (filled($line->merchandise_supplier_order_id)) {
                        throw ValidationException::withMessages([
                            'lines' => __('A line is already on another supplier order.'),
                        ]);
                    }

                    $line->update([
                        'merchandise_supplier_order_id' => $supplierOrder->getKey(),
                    ]);

                    continue;
                }

                if (! $shouldInclude && $isOnThisDraft) {
                    $line->update([
                        'merchandise_supplier_order_id' => null,
                    ]);
                }
            }

            $supplierOrder = $supplierOrder->fresh(['lines', 'supplier', 'team']) ?? $supplierOrder;

            if ($supplierOrder->lines->isEmpty()) {
                $supplierOrder->delete();
            }

            return $supplierOrder;
        });
    }

    /**
     * Ensure a draft exists, then sync selection (creates draft only when including lines).
     *
     * @param  list<string>|Collection<int, string>  $selectedLineIds
     */
    public function syncForSupplier(
        Team $team,
        MerchandiseSupplier $supplier,
        User $user,
        array|Collection $selectedLineIds,
    ): ?MerchandiseSupplierOrder {
        $selectedIds = collect($selectedLineIds)->filter()->unique()->values();

        $draft = MerchandiseSupplierOrder::draftFor($team, $supplier);

        if ($selectedIds->isEmpty()) {
            if (! $draft instanceof MerchandiseSupplierOrder) {
                return null;
            }

            ($this)($draft, $user, []);

            return MerchandiseSupplierOrder::draftFor($team, $supplier);
        }

        $draft ??= ($this->ensureDraft)($team, $supplier, $user);

        return ($this)($draft, $user, $selectedIds->all());
    }
}
