<?php

namespace Database\Factories;

use App\Enums\GirlguidingLevel;
use App\Enums\GirlguidingScheme;
use App\Models\GirlguidingQualification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GirlguidingQualification>
 */
class GirlguidingQualificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scheme' => GirlguidingScheme::Climbing,
            'level' => GirlguidingLevel::One,
        ];
    }
}
