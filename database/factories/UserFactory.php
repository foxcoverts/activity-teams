<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Enums\Section;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'timezone' => 'Europe/London',
            'section' => Section::Adult->value,
            'suspended' => false,
            'oversight' => false,
            'is_system_admin' => false,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['password' => null]);
    }

    public function placeholder(): static
    {
        return $this->state(fn () => [
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
        ]);
    }

    public function guest(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->memberships()->delete();
        });
    }

    public function teamMember(): static
    {
        return $this->afterCreating(function (User $user): void {
            $team = Team::query()->orderByDesc('id')->first() ?? Team::factory()->create();
            $team->grant($user, MembershipRole::Member);
        });
    }

    public function manager(): static
    {
        return $this->afterCreating(function (User $user): void {
            $team = Team::query()->orderByDesc('id')->first() ?? Team::factory()->create();
            $team->grant($user, MembershipRole::Manager);
        });
    }

    public function teamLeader(): static
    {
        return $this->state(fn () => ['is_system_admin' => true])
            ->afterCreating(function (User $user): void {
                $team = Team::factory()->create();
                $team->grant($user, MembershipRole::Manager);
            });
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['suspended' => true])
            ->afterCreating(function (User $user): void {
                $team = Team::query()->orderByDesc('id')->first() ?? Team::factory()->create();
                $team->grant($user, MembershipRole::Member);
            });
    }

    public function oversight(): static
    {
        return $this->state(fn () => ['oversight' => true])->teamMember();
    }
}
