<?php

namespace Database\Factories;

use App\Enums\ScoutPermitActivity;
use App\Enums\ScoutPermitType;
use App\Models\Activity;
use App\Models\ActivityQualificationRequirement;
use App\Models\ScoutPermit;
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
            'detail_type' => ScoutPermit::class,
            'permit_activity' => [ScoutPermitActivity::ClimbingAndAbseiling->value],
            'category' => null,
            'permit_type' => [ScoutPermitType::Leadership->value],
            'award' => null,
            'scheme' => null,
            'level' => null,
            'sort' => 0,
        ];
    }
}
