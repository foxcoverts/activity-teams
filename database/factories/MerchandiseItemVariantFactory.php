<?php

namespace Database\Factories;

use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseItemVariant>
 */
class MerchandiseItemVariantFactory extends Factory
{
    protected $model = MerchandiseItemVariant::class;

    public function definition(): array
    {
        return [
            'merchandise_item_id' => MerchandiseItem::factory(),
            'label' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL', 'One size']),
            'sort' => 0,
        ];
    }
}
