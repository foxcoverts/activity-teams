<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'notes' => $this->faker->optional()->paragraph(),
            'team_id' => fn (): string => Team::query()->orderByDesc('id')->value('id')
                ?? Team::factory()->create()->getKey(),
            'uploader_id' => function (array $attributes) {
                $team = Team::query()->findOrFail($attributes['team_id']);

                $manager = $team->managers()->first();
                if (! $manager) {
                    $manager = User::factory()->teamManager($team)->create();
                }

                return $manager->getKey();
            },
            'file_name' => 'document.pdf',
            'file_path' => function (array $attributes): string {
                $path = 'documents/'.$attributes['team_id'].'/'.Str::ulid().'.pdf';
                Storage::disk('local')->put($path, '%PDF-1.4 test');

                return $path;
            },
            'file_size' => 14,
            'file_type' => 'application/pdf',
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (array $attributes): array => [
            'team_id' => $team->getKey(),
        ]);
    }

    public function trashed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deleted_at' => $this->faker->dateTime(),
        ]);
    }
}
