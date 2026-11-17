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
            'role' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (LogbookEntry $entry): void {
            if ($entry->booking_id === null || $entry->occurred_on !== null) {
                return;
            }

            $entry->loadMissing(['booking.activity', 'booking.venue']);

            if ($entry->booking === null) {
                return;
            }

            $entry->applyBookingSnapshot($entry->booking);
            $entry->saveQuietly();
        });
    }

    public function external(): static
    {
        return $this->state(fn (): array => [
            'booking_id' => null,
            'occurred_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'start_time' => fake()->optional()->time('H:i:s'),
            'timezone' => config('app.timezone'),
            'activity_label' => fake()->randomElement(['Trad Climbing', 'Sport Climbing', 'Indoor Climbing']),
            'venue_name' => fake()->company().' Crag',
            'role' => fake()->optional()->randomElement(['Supervisor', 'Instructor', 'Assistant', 'Personal Climbing']),
        ]);
    }
}
