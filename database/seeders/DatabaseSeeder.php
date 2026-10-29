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
        Activity::query()->firstOrCreate(
            ['slug' => 'climbing'],
            ['name' => 'Climbing', 'description' => 'Climbing and abseiling sessions.']
        );

        Venue::query()->firstOrCreate(
            ['slug' => 'fox-coverts-campsite'],
            [
                'name' => 'Fox Coverts Campsite',
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

        $activity = Activity::query()->where('slug', 'climbing')->firstOrFail();
        $venue = Venue::query()->where('slug', 'fox-coverts-campsite')->firstOrFail();
        $team?->addActivity($activity);
        $team?->addVenue($venue);
    }
}
