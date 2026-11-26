<?php

namespace Database\Factories;

use App\Models\MerchandiseOrder;
use App\Models\MerchandiseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseOrderLine>
 */
class MerchandiseOrderLineFactory extends Factory
{
    protected $model = MerchandiseOrderLine::class;

    public function definition(): array
    {
        return [
            'merchandise_order_id' => MerchandiseOrder::factory(),
            'merchandise_item_id' => null,
            'merchandise_item_variant_id' => null,
            'item_title' => fake()->words(3, true),
            'variant_label' => 'One size',
            'unit_price' => fake()->randomFloat(2, 5, 40),
            'approved_unit_price' => fn (array $attributes): string => number_format((float) $attributes['unit_price'], 2, '.', ''),
            'quantity' => 1,
            'personalisation' => null,
            'sort' => 0,
        ];
    }
}
