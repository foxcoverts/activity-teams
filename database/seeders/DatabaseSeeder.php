<?php

namespace Database\Seeders;

use App\Models\Activity;
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
            ['name' => 'Fox Coverts Campsite', 'notes' => 'Primary campsite venue.']
        );

        $leader = User::factory()->teamLeader()->create([
            'name' => 'Team Leader',
            'email' => 'leader@example.com',
        ]);

        $team = $leader->managedTeams()->first();
        $team?->update(['name' => 'Climbing']);
        $team?->activities()->syncWithoutDetaching(
            Activity::query()->where('slug', 'climbing')->pluck('id')
        );
        $team?->venues()->syncWithoutDetaching(
            Venue::query()->where('slug', 'fox-coverts-campsite')->pluck('id')
        );
    }
}
