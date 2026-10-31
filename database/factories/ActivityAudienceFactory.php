<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Audience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityAudience>
 */
class ActivityAudienceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'audience_id' => Audience::factory(),
        ];
    }
}
