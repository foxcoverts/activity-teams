<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $activity = Activity::query()->firstOrCreate(
            ['name' => 'Climbing'],
            ['description' => 'Climbing and abseiling sessions.']
        );

        $venue = Venue::query()->firstOrCreate(
            ['name' => 'Fox Coverts Campsite'],
            [
                'street' => 'Newbold Road',
                'city' => 'Kirkby Mallory',
                'postcode' => 'LE9 7QG',
                'country' => 'GB',
                'notes' => 'Primary campsite venue.',
            ]
        );

        $team = Team::factory()->create(['name' => 'Climbing', 'slug' => 'climbing']);

        $leader = User::factory()->systemAdmin()->teamManager($team)->create([
            'name' => 'Team Leader',
            'email' => 'leader@example.com',
        ]);

        $team?->addActivity($activity);
        $team?->addVenue($venue);
    }
}
