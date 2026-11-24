<?php

namespace Database\Factories;

use App\Enums\MerchandiseAvailability;
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
            'price' => fake()->randomElement([0, 5, 10, 15, 20, 25]),
            'availability' => MerchandiseAvailability::Available,
            'sort' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'availability' => MerchandiseAvailability::Draft,
        ]);
    }

    public function mothballed(): static
    {
        return $this->state(fn (): array => [
            'availability' => MerchandiseAvailability::Mothballed,
        ]);
    }
}
