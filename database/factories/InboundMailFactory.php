<?php

namespace Database\Factories;

use App\Enums\InboundMailOutcome;
use App\Models\InboundMail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InboundMail>
 */
class InboundMailFactory extends Factory
{
    protected $model = InboundMail::class;

    public function definition(): array
    {
        $from = fake()->safeEmail();
        $to = 'booking+'.(string) Str::ulid().'@climbfoxcoverts.co.uk';
        $subject = fake()->sentence(4);

        $payload = [
            'subject' => $subject,
            'date' => now()->toIso8601String(),
            'from' => [
                'text' => $from,
                'value' => [['address' => $from, 'name' => fake()->name()]],
            ],
            'to' => [
                'text' => $to,
                'value' => [['address' => $to, 'name' => null]],
            ],
            'attachments' => [],
            'html' => '<p>'.fake()->sentence().'</p>',
        ];

        return [
            'body' => json_encode($payload, JSON_THROW_ON_ERROR),
            'from_address' => $from,
            'to_address' => $to,
            'subject' => $subject,
            'outcome' => InboundMailOutcome::Queued,
            'outcome_detail' => __('Needs review'),
        ];
    }

    public function withCalendar(string $ics, string $to): static
    {
        return $this->state(function (array $attributes) use ($ics, $to): array {
            $payload = json_decode($attributes['body'], false, 512, JSON_THROW_ON_ERROR);
            $payload->to = [
                'text' => $to,
                'value' => [['address' => $to, 'name' => null]],
            ];
            $payload->attachments = [[
                'contentType' => 'text/calendar',
                'content' => $ics,
            ]];

            return [
                'body' => json_encode($payload, JSON_THROW_ON_ERROR),
                'to_address' => strtolower($to),
            ];
        });
    }
}
