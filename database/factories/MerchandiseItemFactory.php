<?php

namespace Database\Factories;

use App\Enums\MerchandiseAvailability;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemVariant;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseItem>
 */
class MerchandiseItemFactory extends Factory
{
    protected $model = MerchandiseItem::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'title' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'availability' => MerchandiseAvailability::Available,
        ];
    }

    public function mothballed(): static
    {
        return $this->state(fn (): array => [
            'availability' => MerchandiseAvailability::Mothballed,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (MerchandiseItem $item): void {
            if ($item->variants()->exists()) {
                return;
            }

            MerchandiseItemVariant::factory()->for($item, 'item')->create([
                'label' => 'One size',
                'sort' => 0,
            ]);
        });
    }
}
