<?php

namespace App\Console\Commands;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class SeedDashboardDemoCommand extends Command
{
    protected $signature = 'demo:dashboard {email : Email of the member to seed bookings for}';

    protected $description = 'Create sample bookings for a member so every member dashboard section has something to show (local only)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('This command is for local data only.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $this->argument('email'))->first();
        $team = $user?->teams()->first();

        if (! $user || ! $team) {
            $this->components->error('No member with that email and at least one current team was found.');

            return self::FAILURE;
        }

        Mail::fake();
        Notification::fake();

        $samples = [
            ['+1 day 18:00', BookingAttendeeStatus::Accepted, []],
            ['+2 days 09:30', BookingAttendeeStatus::Accepted, ['lead_instructor_id' => $user->getKey()]],
            ['+3 days 10:00', BookingAttendeeStatus::Tentative, []],
            ['+4 days 19:00', BookingAttendeeStatus::NeedsAction, []],
            ['+5 days 13:00', BookingAttendeeStatus::Accepted, ['lead_instructor_id' => $user->getKey()]],
            ['+10 days 18:00', BookingAttendeeStatus::NeedsAction, []],
            ['+12 days 10:00', BookingAttendeeStatus::Tentative, []],
            ['+14 days 09:00', BookingAttendeeStatus::NeedsAction, []],
            ['+20 days 09:30', BookingAttendeeStatus::Accepted, ['lead_instructor_id' => $user->getKey()]],
        ];

        foreach ($samples as [$when, $status, $attributes]) {
            $start = now('Europe/London')->modify($when);

            $booking = Booking::factory()->forTeam($team)->confirmed()->create([
                'start_at' => $start,
                'end_at' => $start->copy()->addHours(2),
                'group_name' => null,
                ...$attributes,
            ]);

            $booking->attendees()->attach($user->getKey(), ['status' => $status->value]);
        }

        $this->components->info(count($samples)." bookings created for {$user->name} in {$team->name}.");

        return self::SUCCESS;
    }
}
