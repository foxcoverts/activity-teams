<?php

namespace App\Actions;

use App\Models\MerchandiseSupplier;
use App\Models\MerchandiseSupplierOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnsureMerchandiseSupplierOrderDraftAction
{
    public function __invoke(
        Team $team,
        MerchandiseSupplier $supplier,
        User $user,
    ): MerchandiseSupplierOrder {
        if ($supplier->team_id !== $team->getKey()) {
            throw ValidationException::withMessages([
                'supplier' => __('This supplier does not belong to the team.'),
            ]);
        }

        if (! $user->can('create', MerchandiseSupplierOrder::class)) {
            throw ValidationException::withMessages([
                'supplier_order' => __('You cannot manage supplier orders for this team.'),
            ]);
        }

        $existing = MerchandiseSupplierOrder::draftFor($team, $supplier);

        if ($existing instanceof MerchandiseSupplierOrder) {
            return $existing;
        }

        return DB::transaction(function () use ($team, $supplier): MerchandiseSupplierOrder {
            $locked = MerchandiseSupplierOrder::draftFor($team, $supplier);

            if ($locked instanceof MerchandiseSupplierOrder) {
                return $locked;
            }

            return MerchandiseSupplierOrder::query()->create([
                'team_id' => $team->getKey(),
                'merchandise_supplier_id' => $supplier->getKey(),
                'open_draft_key' => MerchandiseSupplierOrder::draftKeyFor($team, $supplier),
            ]);
        });
    }
}
