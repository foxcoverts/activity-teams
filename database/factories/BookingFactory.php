<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 30))->setTime(9, 30);

        return [
            'activity_id' => Activity::factory(),
            'venue_id' => Venue::factory(),
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
            'timezone' => 'Europe/London',
            'status' => BookingStatus::Tentative,
            'group_name' => fake()->company(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Confirmed]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Cancelled]);
    }
}
