<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'country' => 'GB',
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function located(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'street' => fake()->streetAddress(),
                'latitude' => fake()->latitude(),
                'longitude' => fake()->longitude(),
            ];
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Venue $venue): void {
            Team::query()->orderByDesc('id')->first()
                ?->addVenue($venue);
        });
    }
}
