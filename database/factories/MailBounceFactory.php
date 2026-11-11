<?php

namespace Database\Factories;

use App\Models\MailBounce;
use App\Models\OutboundMail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailBounce>
 */
class MailBounceFactory extends Factory
{
    protected $model = MailBounce::class;

    public function definition(): array
    {
        return [
            'outbound_mail_id' => OutboundMail::factory(),
            'provider' => 'forwardemail',
            'provider_message_id' => fake()->unique()->uuid(),
            'category' => 'recipient',
            'action' => 'failed',
            'message' => 'User unknown',
            'hard' => true,
            'bounced_at' => now(),
        ];
    }

    public function soft(): static
    {
        return $this->state(fn (): array => [
            'category' => 'capacity',
            'hard' => false,
            'message' => 'Mailbox full',
        ]);
    }
}
