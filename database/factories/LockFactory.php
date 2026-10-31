<?php

namespace Database\Factories;

use App\Models\Lock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lock>
 */
class LockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
        ];
    }
}
