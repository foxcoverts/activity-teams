<?php

namespace Database\Factories;

use App\Models\MerchandiseSupplier;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseSupplier>
 */
class MerchandiseSupplierFactory extends Factory
{
    protected $model = MerchandiseSupplier::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->company(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
