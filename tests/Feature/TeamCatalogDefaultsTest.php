<?php

namespace Tests\Feature;

use App\Filament\Manage\Resources\Activities\Pages\ListActivities;
use App\Filament\Manage\Resources\Venues\Pages\ListVenues;
use App\Models\Activity;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamCatalogDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('manage'));
    }

    public function test_activity_list_marks_the_default_and_replaces_it_when_another_is_selected(): void
    {
        $user = User::factory()->teamManager()->create();
        $team = $user->managedTeams()->firstOrFail();
        $climbing = Activity::factory()->create(['name' => 'Zebra Climbing']);
        $archery = Activity::factory()->create(['name' => 'Archery']);
        $headquarters = Activity::factory()->create(['name' => 'Headquarters Climbing']);

        $this->assertSame($climbing->getKey(), $team->fresh()->default_activity_id);

        $this->actingAs($user);

        Livewire::test(ListActivities::class)
            ->assertSee('Default')
            ->assertSee('Set as default')
            ->assertSeeHtml('team-default-action')
            ->assertTableColumnDoesNotExist('slug')
            ->assertTableActionHidden('setDefault', $climbing)
            ->assertTableActionVisible('setDefault', $headquarters)
            ->callTableAction('setDefault', $headquarters);

        $this->assertTrue($team->fresh()->defaultActivity->is($headquarters));

        Livewire::test(ListActivities::class)
            ->assertSee('Default')
            ->callTableAction('remove', $headquarters);

        $this->assertTrue($team->fresh()->defaultActivity->is($archery));

        Livewire::test(ListActivities::class)
            ->callTableAction('remove', $archery);

        $this->assertTrue($team->fresh()->defaultActivity->is($climbing));

        Livewire::test(ListActivities::class)
            ->callTableAction('remove', $climbing);

        $this->assertNull($team->fresh()->default_activity_id);
    }

    public function test_venue_list_marks_the_default_and_replaces_it_when_another_is_selected(): void
    {
        $user = User::factory()->teamManager()->create();
        $team = $user->managedTeams()->firstOrFail();
        $campsite = Venue::factory()->create(['name' => 'Woodland Campsite']);
        $alpineCentre = Venue::factory()->create(['name' => 'Alpine Centre']);
        $headquarters = Venue::factory()->create(['name' => 'Headquarters']);

        $this->assertSame($campsite->getKey(), $team->fresh()->default_venue_id);

        $this->actingAs($user);

        Livewire::test(ListVenues::class)
            ->assertSee('Default')
            ->assertSee('Set as default')
            ->assertSeeHtml('team-default-action')
            ->assertTableColumnDoesNotExist('slug')
            ->assertTableActionHidden('setDefault', $campsite)
            ->assertTableActionVisible('setDefault', $headquarters)
            ->callTableAction('setDefault', $headquarters);

        $this->assertTrue($team->fresh()->defaultVenue->is($headquarters));

        Livewire::test(ListVenues::class)
            ->assertSee('Default')
            ->callTableAction('remove', $headquarters);

        $this->assertTrue($team->fresh()->defaultVenue->is($alpineCentre));

        Livewire::test(ListVenues::class)
            ->callTableAction('remove', $alpineCentre);

        $this->assertTrue($team->fresh()->defaultVenue->is($campsite));

        Livewire::test(ListVenues::class)
            ->callTableAction('remove', $campsite);

        $this->assertNull($team->fresh()->default_venue_id);
    }

    public function test_giving_the_first_activity_and_venue_sets_each_as_the_team_default(): void
    {
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $venue = Venue::factory()->create(['name' => 'Campsite']);
        $user = User::factory()->teamManager()->create();
        $team = $user->managedTeams()->firstOrFail();

        $this->actingAs($user);

        Livewire::test(ListActivities::class)
            ->callAction('give', data: [
                'activity_id' => $activity->getKey(),
            ]);
        $team->addVenue($venue);

        $this->assertTrue($team->fresh()->defaultActivity->is($activity));
        $this->assertTrue($team->fresh()->defaultVenue->is($venue));
    }

    public function test_deleting_a_default_activity_and_venue_promotes_another_linked_option(): void
    {
        $user = User::factory()->teamManager()->create();
        $team = $user->managedTeams()->firstOrFail();
        $defaultActivity = Activity::factory()->create(['name' => 'Climbing']);
        $otherActivity = Activity::factory()->create(['name' => 'Archery']);
        $defaultVenue = Venue::factory()->create(['name' => 'Campsite']);
        $otherVenue = Venue::factory()->create(['name' => 'Headquarters']);
        $team->setDefaultActivity($defaultActivity);
        $team->setDefaultVenue($defaultVenue);

        $defaultActivity->delete();
        $defaultVenue->delete();

        $team->refresh();
        $this->assertTrue($team->defaultActivity->is($otherActivity));
        $this->assertTrue($team->defaultVenue->is($otherVenue));
    }

    public function test_the_activity_give_action_is_labelled_for_its_audience(): void
    {
        $manager = User::factory()->teamManager()->create();

        $this->actingAs($manager);
        Livewire::test(ListActivities::class)
            ->assertActionVisible('give')
            ->assertSee('Add an activity')
            ->assertDontSee('Give an activity');

        $this->actingAs(User::factory()->systemAdmin()->create());
        Filament::setTenant($manager->managedTeams()->firstOrFail());
        Livewire::test(ListActivities::class)->assertSee('Give an activity');
    }
}
