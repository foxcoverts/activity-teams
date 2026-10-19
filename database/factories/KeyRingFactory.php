<?php

namespace Database\Factories;

use App\Models\KeyRing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeyRing>
 */
class KeyRingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'holder_id' => null,
        ];
    }

    public function heldBy(User $user): static
    {
        return $this->state(fn (): array => [
            'holder_id' => $user->getKey(),
        ]);
    }
}
