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

    /**
     * Use a team name with an apostrophe for HTML escaping regressions.
     */
    public function withApostropheInName(?string $name = null): static
    {
        return $this->state(function () use ($name): array {
            $resolved = $name ?? "O'Connell Climbing";

            return [
                'name' => $resolved,
                'slug' => Str::slug($resolved).'-'.fake()->unique()->numerify('####'),
            ];
        });
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
