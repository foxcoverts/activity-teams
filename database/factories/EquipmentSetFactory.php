<?php

namespace Database\Factories;

use App\Models\EquipmentSet;
use App\Models\EquipmentSetGroup;
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
            'equipment_set_group_id' => function (array $attributes): string {
                $team = Team::query()->find($attributes['team_id']);

                if (! $team instanceof Team) {
                    $team = Team::factory()->create();
                }

                return EquipmentSetGroup::ensureDefaultFor($team)->getKey();
            },
            'name' => fake()->unique()->words(2, true),
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (): array => [
            'team_id' => $team->getKey(),
            'equipment_set_group_id' => EquipmentSetGroup::ensureDefaultFor($team)->getKey(),
        ]);
    }
}
