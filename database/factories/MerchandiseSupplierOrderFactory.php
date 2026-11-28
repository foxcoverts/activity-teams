<?php

namespace Database\Factories;

use App\Models\MerchandiseSupplier;
use App\Models\MerchandiseSupplierOrder;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseSupplierOrder>
 */
class MerchandiseSupplierOrderFactory extends Factory
{
    protected $model = MerchandiseSupplierOrder::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'merchandise_supplier_id' => fn (array $attributes) => MerchandiseSupplier::factory()->state([
                'team_id' => $attributes['team_id'],
            ]),
            'notes' => null,
            'sent_at' => null,
            'open_draft_key' => function (array $attributes): string {
                $supplierId = $attributes['merchandise_supplier_id'];

                if ($supplierId instanceof MerchandiseSupplier) {
                    $supplierId = $supplierId->getKey();
                }

                return $attributes['team_id'].':'.$supplierId;
            },
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (): array => [
            'team_id' => $team->getKey(),
            'merchandise_supplier_id' => MerchandiseSupplier::factory()->state([
                'team_id' => $team->getKey(),
            ]),
        ]);
    }

    public function forSupplier(MerchandiseSupplier $supplier): static
    {
        return $this->state(fn (): array => [
            'team_id' => $supplier->team_id,
            'merchandise_supplier_id' => $supplier->getKey(),
            'open_draft_key' => MerchandiseSupplierOrder::draftKeyFor(
                $supplier->team ?? Team::query()->findOrFail($supplier->team_id),
                $supplier,
            ),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'sent_at' => now(),
            'open_draft_key' => null,
        ]);
    }
}
