<?php

namespace Database\Factories;

use App\Enums\PersonalDataSection;
use App\Models\PersonalDataAcknowledgement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalDataAcknowledgement>
 */
class PersonalDataAcknowledgementFactory extends Factory
{
    public function definition(): array
    {
        $viewer = User::factory()->create();
        $subject = User::factory()->create();

        return [
            'viewer_id' => $viewer->getKey(),
            'viewer_name' => $viewer->name,
            'subject_id' => $subject->getKey(),
            'subject_name' => $subject->name,
            'section' => PersonalDataSection::ContactDetails,
            'acknowledged_at' => now(),
        ];
    }
}
