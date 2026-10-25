<?php

namespace Database\Factories;

use App\Models\Qualification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Qualification>
 */
class QualificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => 'scout_permit',
            'payload' => [
                'activity' => 'Climbing and Abseiling',
                'category' => 'Artificial Top Rope',
                'permit_type' => 'leadership',
            ],
            'expires_on' => now()->addYear()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function scoutPermit(array $payload = []): static
    {
        return $this->state(fn (): array => [
            'kind' => 'scout_permit',
            'payload' => [
                'activity' => 'Climbing and Abseiling',
                'category' => 'Artificial Top Rope',
                'permit_type' => 'leadership',
                ...$payload,
            ],
            'expires_on' => now()->addYear()->toDateString(),
        ]);
    }

    public function archery(): static
    {
        return $this->scoutPermit([
            'activity' => 'Archery',
            'category' => 'without Compound Bows',
            'permit_type' => 'leadership',
        ]);
    }

    public function mountainTraining(string $award = 'Climbing Wall Instructor'): static
    {
        return $this->state(fn (): array => [
            'kind' => 'mountain_training',
            'payload' => ['award' => $award],
            'expires_on' => null,
        ]);
    }

    public function girlguiding(string $level = '1', string $scheme = 'Climbing'): static
    {
        return $this->state(fn (): array => [
            'kind' => 'girlguiding',
            'payload' => [
                'scheme' => $scheme,
                'level' => $level,
            ],
            'expires_on' => now()->addYear()->toDateString(),
        ]);
    }

    public function nsra(string $award = 'Club Instructor'): static
    {
        return $this->state(fn (): array => [
            'kind' => 'nsra',
            'payload' => ['award' => $award],
            'expires_on' => now()->addYear()->toDateString(),
        ]);
    }

    public function archeryGb(string $award = 'Instructor Award'): static
    {
        return $this->state(fn (): array => [
            'kind' => 'archery_gb_awards',
            'payload' => ['award' => $award],
            'expires_on' => now()->addYear()->toDateString(),
        ]);
    }

    public function forMember(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->getKey(),
        ]);
    }
}
