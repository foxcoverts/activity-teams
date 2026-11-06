<?php

namespace Tests\Feature\Teams;

use App\Filament\Teams\Resources\Keys\Pages\ListKeys;
use App\Models\Activity;
use App\Models\Key;
use App\Models\Lock;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KeyResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_the_key_ring_list_is_sorted_by_name(): void
    {
        $manager = User::factory()->teamManager()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $key = Lock::factory()->create();
        $key->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);
        $zebra = Key::factory()->create(['name' => 'Zebra']);
        $zebra->locks()->attach($key);
        $archery = Key::factory()->create(['name' => 'Archery']);
        $archery->locks()->attach($key);

        $this->actingAs($manager);

        Livewire::test(ListKeys::class)
            ->assertCanSeeTableRecords([$archery, $zebra], inOrder: true);
    }

    public function test_the_holder_filter_lists_only_holders_of_keys_on_this_team(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->teamManager($team)->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $lock = Lock::factory()->create();
        $lock->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);

        $otherTeam = Team::factory()->create();
        $otherActivity = Activity::factory()->create();
        $otherVenue = Venue::factory()->create();
        $otherLock = Lock::factory()->create();
        $otherLock->requirements()->create([
            'activity_id' => $otherActivity->getKey(),
            'venue_id' => $otherVenue->getKey(),
        ]);

        $holder = User::factory()->create(['name' => 'Team Holder']);
        $memberElsewhere = User::factory()->teamMember($team)->create(['name' => 'Member Elsewhere']);
        $suspended = User::factory()->create([
            'name' => 'Suspended Holder',
            'suspended' => true,
        ]);

        $teamKey = Key::factory()->heldBy($holder)->create();
        $teamKey->locks()->attach($lock);
        $otherKey = Key::factory()->heldBy($memberElsewhere)->create();
        $otherKey->locks()->attach($otherLock);
        $suspendedKey = Key::factory()->heldBy($suspended)->create();
        $suspendedKey->locks()->attach($lock);

        $this->actingAs($manager);

        $names = Livewire::test(ListKeys::class)
            ->instance()
            ->getTable()
            ->getFilter('holder_id')
            ->getRelationshipQuery()
            ->pluck('name');

        $this->assertTrue($names->contains($holder->name));
        $this->assertFalse($names->contains($memberElsewhere->name));
        $this->assertFalse($names->contains($suspended->name));
    }
}
