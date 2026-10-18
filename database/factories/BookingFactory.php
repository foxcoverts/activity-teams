<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Team;
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
            'team_id' => fn (): string => Team::query()->orderByDesc('id')->value('id')
                ?? Team::factory()->create()->getKey(),
            'activity_id' => function (array $attributes): string {
                $activity = Activity::factory()->create();
                Team::query()->findOrFail($attributes['team_id'])->activities()->syncWithoutDetaching([$activity->getKey()]);

                return (string) $activity->getKey();
            },
            'venue_id' => function (array $attributes): string {
                $venue = Venue::factory()->create();
                Team::query()->findOrFail($attributes['team_id'])->venues()->syncWithoutDetaching([$venue->getKey()]);

                return (string) $venue->getKey();
            },
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

    public function configure(): static
    {
        return $this->afterMaking(function (Booking $booking): void {
            $team = Team::query()->find($booking->team_id);

            if ($team === null) {
                return;
            }

            if (filled($booking->activity_id)) {
                $team->activities()->syncWithoutDetaching([$booking->activity_id]);
            }

            if (filled($booking->venue_id)) {
                $team->venues()->syncWithoutDetaching([$booking->venue_id]);
            }
        });
    }
}
