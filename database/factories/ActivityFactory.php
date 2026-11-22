<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Audience;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'description' => fake()->optional()->sentence(),
            'requires_activity_label' => false,
        ];
    }

    public function requiresActivityLabel(): static
    {
        return $this->state(fn (): array => [
            'requires_activity_label' => true,
        ]);
    }

    /**
     * Offer only this audience (admin-create shape: audience first, then activity paired to it).
     *
     * @param  list<array{kind: string, criteria?: array<string, mixed>}>  $qualificationRequirements
     */
    public function withAudience(Audience $audience, array $qualificationRequirements = []): static
    {
        return $this->afterCreating(function (Activity $activity) use ($audience, $qualificationRequirements): void {
            $activity->activityAudiences()->with('audience')->get()->each(function (ActivityAudience $pairing) use ($audience): void {
                if ($pairing->audience_id === $audience->getKey()) {
                    return;
                }

                $other = $pairing->audience;
                $pairing->delete();

                if ($other instanceof Audience && $other->activityAudiences()->doesntExist()) {
                    $other->delete();
                }
            });

            $pairing = ActivityAudience::query()->firstOrCreate([
                'activity_id' => $activity->getKey(),
                'audience_id' => $audience->getKey(),
            ]);

            foreach ($qualificationRequirements as $requirement) {
                $pairing->qualificationRequirements()->create($requirement);
            }
        });
    }

    /**
     * Attach another audience after {@see withAudience()} (Audiences-tab shape).
     *
     * @param  list<array{kind: string, criteria?: array<string, mixed>}>  $qualificationRequirements
     */
    public function andAudience(Audience $audience, array $qualificationRequirements = []): static
    {
        return $this->afterCreating(function (Activity $activity) use ($audience, $qualificationRequirements): void {
            $pairing = ActivityAudience::query()->firstOrCreate([
                'activity_id' => $activity->getKey(),
                'audience_id' => $audience->getKey(),
            ]);

            foreach ($qualificationRequirements as $requirement) {
                $pairing->qualificationRequirements()->create($requirement);
            }
        });
    }

    /**
     * Leave the activity with no audience pairings (for broken-catalog fixtures).
     */
    public function withoutAudiences(): static
    {
        return $this->afterCreating(function (Activity $activity): void {
            $activity->activityAudiences()->with('audience')->get()->each(function (ActivityAudience $pairing): void {
                $audience = $pairing->audience;
                $pairing->delete();

                if ($audience instanceof Audience && $audience->activityAudiences()->doesntExist()) {
                    $audience->delete();
                }
            });
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Activity $activity): void {
            if (! $activity->hasAudiences()) {
                ActivityAudience::factory()->for($activity)->create();
            }

            Team::query()->orderByDesc('id')->first()
                ?->addActivity($activity);
        });
    }
}
