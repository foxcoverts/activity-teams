<?php

namespace Database\Factories;

use App\Models\EquipmentSetGroup;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentSetGroup>
 */
class EquipmentSetGroupFactory extends Factory
{
    protected $model = EquipmentSetGroup::class;

    public function definition(): array
    {
        return [
            'team_id' => fn (): string => Team::query()->orderByDesc('id')->value('id')
                ?? Team::factory()->create()->getKey(),
            'label' => fake()->unique()->words(2, true),
            'rank' => fn (array $attributes): int => EquipmentSetGroup::query()
                ->where('team_id', $attributes['team_id'])
                ->max('rank') + 1,
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (): array => ['team_id' => $team->getKey()]);
    }
}
