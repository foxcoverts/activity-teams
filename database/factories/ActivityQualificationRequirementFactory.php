<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityQualificationRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityQualificationRequirement>
 */
class ActivityQualificationRequirementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Climbing and Abseiling',
                'permit_type' => 'leadership',
            ],
            'sort' => 0,
        ];
    }
}
