<?php

namespace Database\Factories;

use App\Models\NewsPost;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsPost>
 */
class NewsPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'slug' => fn (array $attributes): string => Str::slug($attributes['title']),
            'body' => $this->faker->paragraph(),
            'published_at' => null,
            'notified_at' => null,
            'team_id' => fn (): string => Team::query()->orderByDesc('id')->value('id')
                ?? Team::factory()->create()->getKey(),
            'author_id' => function (array $attributes) {
                $team = Team::query()->findOrFail($attributes['team_id']);

                $manager = $team->managers()->first();
                if (! $manager) {
                    $manager = User::factory()->teamManager($team)->create();
                }

                return $manager->getKey();
            },
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes): array => [
            'team_id' => $team->getKey(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => $this->faker->dateTime(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => $this->faker->dateTimeBetween('tomorrow', '+1 year'),
        ]);
    }

    public function notified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'notified_at' => $attributes['published_at'] ?? now(),
        ]);
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deleted_at' => $this->faker->dateTime(),
        ]);
    }
}
