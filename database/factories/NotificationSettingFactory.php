<?php

namespace Database\Factories;

use App\Enums\MailChannel;
use App\Models\Booking;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationSetting>
 */
class NotificationSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject_id' => Booking::factory(),
            'subject_type' => (new Booking)->getMorphClass(),
            'preferences' => [
                MailChannel::BookingsMail->value => false,
            ],
        ];
    }
}
