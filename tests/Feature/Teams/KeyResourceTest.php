<?php

namespace Tests\Feature\Teams;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Teams\Resources\Keys\Pages\ListKeys;
use App\Filament\Teams\Resources\Keys\Pages\ViewKey;
use App\Filament\Teams\Resources\Members\MemberResource;
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

    public function test_the_key_list_hides_unlocks_on_small_screens_and_links_the_holder(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $holder = User::factory()->teamMember($team)->create(['name' => 'Casey Holder']);
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $lock = Lock::factory()->create(['name' => 'Store cupboard']);
        $lock->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);
        $ring = Key::factory()->heldBy($holder)->create(['name' => 'Store key']);
        $ring->locks()->attach($lock);

        $this->actingAs($manager);

        $memberUrl = MemberResource::getUrl('view', ['record' => $holder], panel: 'teams', tenant: $team);
        $avatarUrl = (string) $holder->getFilamentAvatarUrl();

        $list = Livewire::test(ListKeys::class)
            ->assertCanSeeTableRecords([$ring])
            ->assertSee('Casey Holder')
            ->assertSeeHtml($memberUrl)
            ->assertSee('fi-ta-person', false)
            ->assertSee(str_replace('&', '&amp;', $avatarUrl), false);

        $this->assertSame('md', $list->instance()->getTable()->getColumn('unlocks')->getVisibleFrom());
    }

    public function test_the_key_view_shows_the_holder_avatar_and_member_link(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $holder = User::factory()->teamMember($team)->create(['name' => 'Casey Holder']);
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $lock = Lock::factory()->create(['name' => 'Store cupboard']);
        $lock->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);
        $ring = Key::factory()->heldBy($holder)->create(['name' => 'Store key']);
        $ring->locks()->attach($lock);

        $this->actingAs($manager);

        $memberUrl = MemberResource::getUrl('view', ['record' => $holder], panel: 'teams', tenant: $team);
        $avatarUrl = (string) $holder->getFilamentAvatarUrl();

        Livewire::test(ViewKey::class, ['record' => $ring->getRouteKey()])
            ->assertSee('Casey Holder')
            ->assertSeeHtml($memberUrl)
            ->assertSee('fi-in-person', false)
            ->assertSee(str_replace('&', '&amp;', $avatarUrl), false);
    }

    public function test_the_key_views_show_admins_links_to_user_view(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();
        $outsideHolder = User::factory()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $lock = Lock::factory()->create(['name' => 'Store cupboard']);
        $lock->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);
        $ring = Key::factory()->heldBy($outsideHolder)->create(['name' => 'Store key']);
        $ring->locks()->attach($lock);

        $this->actingAs($admin);
        Filament::setTenant($team);

        $userUrl = UserResource::getUrl('view', ['record' => $outsideHolder], panel: 'admin');

        Livewire::test(ListKeys::class)
            ->assertSuccessful()
            ->assertSeeText($outsideHolder->name)
            ->assertSeeHtml('href="'.$userUrl.'"');

        Livewire::test(ViewKey::class, ['record' => $ring->getRouteKey()])
            ->assertSuccessful()
            ->assertSeeText($outsideHolder->name)
            ->assertSeeHtml('href="'.$userUrl.'"');
    }

    public function test_the_key_views_prefer_member_links_for_admins_when_the_holder_is_on_the_team(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();
        $holder = User::factory()->teamMember($team)->create(['name' => 'Casey Holder']);
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $lock = Lock::factory()->create(['name' => 'Store cupboard']);
        $lock->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);
        $ring = Key::factory()->heldBy($holder)->create(['name' => 'Store key']);
        $ring->locks()->attach($lock);

        $this->actingAs($admin);
        Filament::setTenant($team);

        $memberUrl = MemberResource::getUrl('view', ['record' => $holder], panel: 'teams', tenant: $team);
        $userUrl = UserResource::getUrl('view', ['record' => $holder], panel: 'admin');

        Livewire::test(ListKeys::class)
            ->assertSuccessful()
            ->assertSeeText($holder->name)
            ->assertSeeHtml('href="'.$memberUrl.'"')
            ->assertDontSeeHtml('href="'.$userUrl.'"');

        Livewire::test(ViewKey::class, ['record' => $ring->getRouteKey()])
            ->assertSuccessful()
            ->assertSeeText($holder->name)
            ->assertSeeHtml('href="'.$memberUrl.'"')
            ->assertDontSeeHtml('href="'.$userUrl.'"');
    }

    public function test_the_key_views_do_not_show_managers_links_to_outside_holders(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->teamManager($team)->create();
        $outsideHolder = User::factory()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $lock = Lock::factory()->create(['name' => 'Store cupboard']);
        $lock->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);
        $ring = Key::factory()->heldBy($outsideHolder)->create(['name' => 'Store key']);
        $ring->locks()->attach($lock);
        $this->actingAs($manager);

        $memberUrl = MemberResource::getUrl('view', ['record' => $outsideHolder], panel: 'teams', tenant: $team);
        $userUrl = UserResource::getUrl('view', ['record' => $outsideHolder], panel: 'admin');

        Livewire::test(ListKeys::class)
            ->assertSuccessful()
            ->assertSeeText($outsideHolder->name)
            ->assertDontSeeHtml('href="'.$memberUrl.'"')
            ->assertDontSeeHtml('href="'.$userUrl.'"');

        Livewire::test(ViewKey::class, ['record' => $ring->getRouteKey()])
            ->assertSuccessful()
            ->assertSeeText($outsideHolder->name)
            ->assertDontSeeHtml('href="'.$memberUrl.'"')
            ->assertDontSeeHtml('href="'.$userUrl.'"');
    }
}
