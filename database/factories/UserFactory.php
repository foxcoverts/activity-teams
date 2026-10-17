<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Enums\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
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
            'role' => Role::TeamMember->value,
            'accreditations' => '',
            'section' => Section::Adult->value,
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

    public function guest(): static
    {
        return $this->state(fn () => ['role' => Role::Guest->value]);
    }

    public function teamMember(): static
    {
        return $this->state(fn () => ['role' => Role::TeamMember->value]);
    }

    public function teamLeader(): static
    {
        return $this->state(fn () => ['role' => Role::TeamLeader->value]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['role' => Role::Suspended->value]);
    }

    public function anyRoleExcept(Role $role): static
    {
        $roles = Arr::reject(Role::cases(), fn (Role $value): bool => $value === $role);

        return $this->state(fn () => [
            'role' => fake()->randomElement($roles)->value,
        ]);
    }
}
