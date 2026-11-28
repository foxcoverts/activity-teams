<?php

namespace Database\Factories;

use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemOptionAxis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseItemOptionAxis>
 */
class MerchandiseItemOptionAxisFactory extends Factory
{
    protected $model = MerchandiseItemOptionAxis::class;

    public function definition(): array
    {
        return [
            'merchandise_item_id' => MerchandiseItem::factory(),
            'name' => fake()->randomElement(['Size', 'Colour', 'Style']),
            'sort' => 0,
        ];
    }
}
