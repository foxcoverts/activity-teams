<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Lock;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * @extends Factory<Lock>
 */
class LockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
        ];
    }

    /**
     * Add a Required-for row (Admin lock create repeater shape). Chain for multiple rows.
     */
    public function requiredFor(Activity|string $activity, Venue|string $venue): static
    {
        return $this->afterCreating(function (Lock $lock) use ($activity, $venue): void {
            $lock->requirements()->create([
                'activity_id' => $activity instanceof Activity ? $activity->getKey() : $activity,
                'venue_id' => $venue instanceof Venue ? $venue->getKey() : $venue,
            ]);
        });
    }

    /**
     * Cover other locks (Admin lock create Covers shape).
     *
     * @param  Lock|iterable<int, Lock>  $locks
     */
    public function covers(Lock|iterable $locks): static
    {
        $covered = Collection::wrap($locks);

        return $this->afterCreating(function (Lock $lock) use ($covered): void {
            $lock->covers()->syncWithoutDetaching(
                $covered->map(fn (Lock $other): string => (string) $other->getKey())->all(),
            );
        });
    }
}
