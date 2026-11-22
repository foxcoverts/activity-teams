<?php

namespace Database\Factories;

use App\Models\Key;
use App\Models\Lock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;

/**
 * @extends Factory<Key>
 */
class KeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'holder_id' => null,
        ];
    }

    public function heldBy(User $user): static
    {
        return $this->state(fn (): array => [
            'holder_id' => $user->getKey(),
        ]);
    }

    /**
     * Unlock these locks (Admin key create Unlocks shape).
     *
     * @param  Lock|iterable<int, Lock>  $locks
     */
    public function unlocks(Lock|iterable $locks): static
    {
        $targets = Collection::wrap($locks);

        return $this->afterCreating(function (Key $key) use ($targets): void {
            $key->locks()->syncWithoutDetaching(
                $targets->map(fn (Lock $lock): string => (string) $lock->getKey())->all(),
            );
        });
    }
}
