<?php

namespace Database\Factories;

use App\Models\Key;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Key>
 */
class KeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
        ];
    }
}
