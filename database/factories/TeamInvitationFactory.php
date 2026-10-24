<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TeamInvitation>
 */
class TeamInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory()->placeholder(),
            'email' => fake()->unique()->safeEmail(),
            'token' => Str::random(40),
            'sent_at' => now(),
            'expires_at' => now()->addDays(TeamInvitation::VALID_DAYS),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function declined(): static
    {
        return $this->state(fn () => ['declined_at' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function siteAccess(bool $oversight = false, bool $systemAdmin = true): static
    {
        return $this->state(fn (): array => [
            'team_id' => null,
        ])->afterCreating(function (TeamInvitation $invitation) use ($oversight, $systemAdmin): void {
            $invitation->user->forceFill([
                'oversight' => $oversight,
                'is_system_admin' => $systemAdmin,
            ])->save();
        });
    }
}
