<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Booking;
use App\Models\Team;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

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
                Team::query()->findOrFail($attributes['team_id'])->addActivity($activity);

                return (string) $activity->getKey();
            },
            'venue_id' => function (array $attributes): string {
                $venue = Venue::factory()->create();
                Team::query()->findOrFail($attributes['team_id'])->addVenue($venue);

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

    public function forTeam(Team $team): static
    {
        return $this->state(fn () => ['team_id' => $team->getKey()]);
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
                $activity = Activity::query()->findOrFail($booking->activity_id);
                $team->addActivity($activity);
                $booking->audience_id = filled($booking->audience_id)
                    ? $booking->audience_id
                    : static::audienceFor($activity);

                static::assertActivityOffersAudience($activity, (string) $booking->audience_id);
            }

            if (filled($booking->venue_id)) {
                $venue = Venue::query()->findOrFail($booking->venue_id);
                $team->addVenue($venue);
            }
        });
    }

    private static function audienceFor(Activity $activity): string
    {
        $existing = ActivityAudience::query()
            ->where('activity_id', $activity->getKey())
            ->orderBy('id')
            ->value('audience_id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        throw new RuntimeException(
            'BookingFactory needs an activity that already offers an audience. Use Activity::factory()->withAudience(...).',
        );
    }

    private static function assertActivityOffersAudience(Activity $activity, string $audienceId): void
    {
        $offers = ActivityAudience::query()
            ->where('activity_id', $activity->getKey())
            ->where('audience_id', $audienceId)
            ->exists();

        if ($offers) {
            return;
        }

        throw new RuntimeException(
            'BookingFactory needs the activity to already offer audience '.$audienceId.'. Pair it with withAudience() / andAudience() first.',
        );
    }
}
