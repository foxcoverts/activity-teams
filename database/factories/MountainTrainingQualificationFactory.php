<?php

namespace Database\Factories;

use App\Enums\MountainTrainingAward;
use App\Models\MountainTrainingQualification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MountainTrainingQualification>
 */
class MountainTrainingQualificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'award' => MountainTrainingAward::ClimbingWallInstructor,
        ];
    }
}
