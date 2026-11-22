<?php

namespace Tests\Feature\Teams;

use App\Filament\RelationManagers\QualificationsRelationManager;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Filament\Teams\Resources\Members\Pages\ListMembers;
use App\Filament\Teams\Resources\Members\Pages\ViewMember;
use App\Models\Activity;
use App\Models\Key;
use App\Models\Lock;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_the_member_list_is_sorted_by_name(): void
    {
        $manager = User::factory()->teamManager()->create(['name' => 'Casey Manager']);
        $team = $manager->managedTeams()->firstOrFail();
        $zebra = User::factory()->teamMember($team)->create(['name' => 'Zebra']);
        $alex = User::factory()->teamMember($team)->create(['name' => 'Alex']);

        $this->actingAs($manager);

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$alex, $manager, $zebra], inOrder: true);
    }

    public function test_view_defaults_relation_tab_to_qualifications_when_keys_are_also_shown(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->teamMember($team)->create();

        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $team->activities()->sync([$activity->getKey()]);
        $team->venues()->sync([$venue->getKey()]);

        $store = Lock::factory()
            ->requiredFor($activity, $venue)
            ->create(['name' => 'Climbing store']);
        Key::factory()->heldBy($member)->unlocks($store)->create(['name' => 'Shared climbing']);

        $this->actingAs($manager);

        $key = array_search(QualificationsRelationManager::class, MemberResource::getRelations(), true);
        $this->assertNotFalse($key);

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->assertSet('activeRelationManager', $key);
    }
}
