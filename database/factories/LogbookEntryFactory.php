<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\LogbookEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogbookEntry>
 */
class LogbookEntryFactory extends Factory
{
    protected $model = LogbookEntry::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'booking_id' => Booking::factory(),
            'hours' => fake()->optional()->randomFloat(2, 1, 8),
            'participant_count' => fake()->optional()->numberBetween(1, 20),
            'weather' => fake()->optional()->randomElement(['Dry', 'Wet', 'Windy', 'Clear', 'Overcast']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
