<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingDebrief;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingDebrief>
 */
class BookingDebriefFactory extends Factory
{
    protected $model = BookingDebrief::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'hours' => fake()->randomFloat(2, 1, 8),
            'participant_count' => fake()->numberBetween(1, 20),
            'weather' => fake()->randomElement(['Dry', 'Wet', 'Windy', 'Clear', 'Overcast']),
            'notes' => fake()->optional()->sentence(),
            'completed_at' => now(),
            'completed_by_id' => User::factory(),
        ];
    }
}
