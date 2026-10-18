<?php

namespace Database\Factories;

use App\Enums\ScoutPermitActivity;
use App\Enums\ScoutPermitCategory;
use App\Enums\ScoutPermitType;
use App\Models\ScoutPermit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoutPermit>
 */
class ScoutPermitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'activity' => ScoutPermitActivity::ClimbingAndAbseiling,
            'category' => ScoutPermitCategory::ArtificialTopRope,
            'permit_type' => ScoutPermitType::Leadership,
            'restrictions' => null,
        ];
    }

    public function archery(): static
    {
        return $this->state(fn (): array => [
            'activity' => ScoutPermitActivity::Archery,
            'category' => null,
        ]);
    }
}
