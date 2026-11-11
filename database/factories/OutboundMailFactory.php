<?php

namespace Database\Factories;

use App\Enums\OutboundMailStatus;
use App\Models\OutboundMail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OutboundMail>
 */
class OutboundMailFactory extends Factory
{
    protected $model = OutboundMail::class;

    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'message_id' => strtolower($uuid.'@example.test'),
            'recipient' => fake()->unique()->safeEmail(),
            'user_id' => null,
            'status' => OutboundMailStatus::Sent,
            'mailable' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'recipient' => strtolower((string) $user->email),
            'user_id' => $user->getKey(),
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn (): array => [
            'status' => OutboundMailStatus::Bounced,
        ]);
    }
}
