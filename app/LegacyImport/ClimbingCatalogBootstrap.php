<?php

namespace App\LegacyImport;

use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\ActivityQualificationRequirement;
use App\Models\Audience;
use App\Models\Lock;
use App\Models\LockRequirement;
use App\Models\Team;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds the Fox Coverts Climbing catalog shape used by legacy import.
 */
final class ClimbingCatalogBootstrap
{
    public function run(): Team
    {
        $scouts = Audience::query()->firstOrCreate(['name' => 'Scouts'], ['description' => null]);
        $girlguides = Audience::query()->firstOrCreate(['name' => 'Girlguides'], ['description' => null]);
        $public = Audience::query()->firstOrCreate(['name' => 'Public'], ['description' => null]);
        $teamAudience = Audience::query()->firstOrCreate(['name' => 'Team'], ['description' => null]);

        $climbing = Activity::query()->firstOrCreate(
            ['name' => 'Climbing'],
            ['description' => 'Climbing and abseiling sessions.', 'requires_activity_label' => false],
        );

        $climbingAccess = Activity::query()->firstOrCreate(
            ['name' => 'Climbing Access'],
            ['description' => 'Tower access sessions that need keys but not a session title from the catalog.', 'requires_activity_label' => true],
        );
        $climbingAccess->forceFill(['requires_activity_label' => true])->save();

        $fox = Venue::query()->firstOrCreate(
            ['name' => 'Fox Coverts Campsite'],
            [
                'street' => 'Newbold Road',
                'city' => 'Kirkby Mallory',
                'postcode' => 'LE9 7QG',
                'country' => 'GB',
                'notes' => 'Primary campsite venue.',
            ],
        );

        $lopc = Venue::query()->firstOrCreate(
            ['name' => 'Leicester Outdoor Pursuits Centre'],
            [
                'street' => 'Loughborough Rd',
                'city' => 'Leicester',
                'postcode' => 'LE4 5PN',
                'country' => 'GB',
                'latitude' => 52.6634794,
                'longitude' => -1.1268926,
            ],
        );

        $climbingLock = Lock::query()->firstOrCreate(['name' => 'Campsite Climbing']);
        $wardenLock = Lock::query()->firstOrCreate(['name' => 'Campsite Warden']);
        $wardenLock->covers()->syncWithoutDetaching([$climbingLock->getKey()]);

        $this->ensurePairing($climbing, $scouts, [
            ['kind' => 'scout_permit', 'criteria' => ['activity' => 'Climbing and Abseiling', 'permit_type' => 'leadership'], 'sort' => 1],
        ]);
        $this->ensurePairing($climbing, $girlguides, [
            ['kind' => 'mountain_training', 'criteria' => ['award' => 'Climbing Wall Instructor'], 'sort' => 0],
            ['kind' => 'girlguiding', 'criteria' => ['scheme' => 'Climbing'], 'sort' => 0],
        ]);
        $this->ensurePairing($climbing, $public, [
            ['kind' => 'mountain_training', 'criteria' => ['award' => 'Climbing Wall Instructor'], 'sort' => 0],
        ]);
        $this->ensurePairing($climbing, $teamAudience, [
            ['kind' => 'scout_permit', 'criteria' => ['activity' => 'Climbing and Abseiling', 'permit_type' => 'leadership'], 'sort' => 1],
        ]);
        $this->ensurePairing($climbingAccess, $teamAudience, []);

        $this->ensureLockRequirement($climbingLock, $climbing, $fox);
        $this->ensureLockRequirement($climbingLock, $climbingAccess, $fox);

        $team = Team::query()->firstOrCreate(
            ['slug' => 'climbing'],
            ['name' => 'Climbing', 'color' => 'sky'],
        );
        $team->forceFill(['name' => 'Climbing'])->save();

        $team->addActivity($climbing);
        $team->addActivity($climbingAccess);
        $team->addVenue($fox);
        $team->addVenue($lopc);
        $team->setDefaultActivity($climbing);
        $team->setDefaultVenue($fox);
        $team->setDefaultAudience($climbing, $scouts);
        $team->setDefaultAudience($climbingAccess, $teamAudience);

        return $team->fresh();
    }

    /**
     * @param  list<array{kind: string, criteria: array<string, mixed>, sort: int}>  $requirements
     */
    private function ensurePairing(Activity $activity, Audience $audience, array $requirements): void
    {
        $pairing = ActivityAudience::query()->firstOrCreate([
            'activity_id' => $activity->getKey(),
            'audience_id' => $audience->getKey(),
        ]);

        $existing = ActivityQualificationRequirement::query()
            ->where('activity_audience_id', $pairing->getKey())
            ->get();

        if ($requirements === []) {
            $existing->each->delete();

            return;
        }

        if ($existing->isNotEmpty()) {
            return;
        }

        foreach ($requirements as $requirement) {
            ActivityQualificationRequirement::query()->create([
                'id' => (string) Str::ulid(),
                'activity_audience_id' => $pairing->getKey(),
                'kind' => $requirement['kind'],
                'criteria' => $requirement['criteria'],
                'sort' => $requirement['sort'],
            ]);
        }
    }

    private function ensureLockRequirement(Lock $lock, Activity $activity, Venue $venue): void
    {
        $exists = LockRequirement::query()
            ->where('lock_id', $lock->getKey())
            ->where('activity_id', $activity->getKey())
            ->where('venue_id', $venue->getKey())
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('lock_requirements')->insert([
            'id' => (string) Str::ulid(),
            'lock_id' => $lock->getKey(),
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
