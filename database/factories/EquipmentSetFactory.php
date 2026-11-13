<?php

namespace Database\Factories;

use App\Models\EquipmentSet;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentSet>
 */
class EquipmentSetFactory extends Factory
{
    protected $model = EquipmentSet::class;

    public function definition(): array
    {
        return [
            'team_id' => fn (): string => Team::query()->orderByDesc('id')->value('id')
                ?? Team::factory()->create()->getKey(),
            'name' => fake()->unique()->words(2, true),
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (): array => ['team_id' => $team->getKey()]);
    }
}
