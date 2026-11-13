<?php

namespace Database\Factories;

use App\Enums\TeamFeature;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'color' => null,
            'features' => [],
        ];
    }

    public function withTasks(): static
    {
        return $this->state(fn (): array => [
            'features' => [TeamFeature::Tasks->value => true],
        ]);
    }

    public function withDebrief(): static
    {
        return $this->state(fn (): array => [
            'features' => [
                TeamFeature::Debrief->value => [
                    'enabled' => true,
                    'activated_at' => now()->subYear()->toIso8601String(),
                ],
            ],
        ]);
    }
}
