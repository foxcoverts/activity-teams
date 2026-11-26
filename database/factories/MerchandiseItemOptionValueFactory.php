<?php

namespace Database\Factories;

use App\Models\MerchandiseItemOptionAxis;
use App\Models\MerchandiseItemOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseItemOptionValue>
 */
class MerchandiseItemOptionValueFactory extends Factory
{
    protected $model = MerchandiseItemOptionValue::class;

    public function definition(): array
    {
        return [
            'merchandise_item_option_axis_id' => MerchandiseItemOptionAxis::factory(),
            'label' => fake()->word(),
            'sort' => 0,
        ];
    }
}
