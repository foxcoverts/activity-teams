<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\Activities\Pages\ViewActivity;
use App\Filament\Admin\Resources\Activities\RelationManagers\TeamsRelationManager as ActivityTeamsRelationManager;
use App\Filament\Admin\Resources\Venues\Pages\ViewVenue;
use App\Filament\Admin\Resources\Venues\RelationManagers\TeamsRelationManager as VenueTeamsRelationManager;
use App\Filament\Teams\Resources\Activities\ActivityResource as TeamsActivityResource;
use App\Filament\Teams\Resources\Venues\VenueResource as TeamsVenueResource;
use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Audience;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogTeamsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_admin_can_give_and_remove_an_activity_from_a_team(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $audience = Audience::factory()->create(['name' => 'Scouts']);
        ActivityAudience::factory()->for($activity)->for($audience)->create();
        $team = Team::factory()->create(['name' => 'Fox Coverts']);

        $this->actingAs($admin);

        Livewire::test(ActivityTeamsRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => ViewActivity::class,
        ])
            ->callAction(TestAction::make('give')->table(), [
                'team_id' => $team->getKey(),
                'default_audience_id' => $audience->getKey(),
                'set_as_default' => true,
            ])
            ->assertDispatched('refresh-sidebar')
            ->assertDispatched('refresh-topbar')
            ->assertSee('Fox Coverts')
            ->assertSee('Default activity')
            ->assertSee('Scouts')
            ->assertSeeHtml(TeamsActivityResource::getUrl('index', panel: 'teams', tenant: $team));

        $this->assertTrue($team->fresh()->defaultActivity->is($activity));
        $this->assertTrue($team->fresh()->defaultAudienceFor($activity)?->is($audience));

        Livewire::test(ActivityTeamsRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => ViewActivity::class,
        ])
            ->callAction(TestAction::make('remove')->table($team))
            ->assertDispatched('refresh-sidebar')
            ->assertDispatched('refresh-topbar');

        $this->assertFalse($team->fresh()->activities()->whereKey($activity->getKey())->exists());
        $this->assertNull($team->fresh()->default_activity_id);
    }

    public function test_admin_giving_an_activity_can_replace_an_existing_team_default(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $existing = Activity::factory()->create(['name' => 'Archery']);
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $team = Team::factory()->create();
        $team->addActivity($existing);

        $this->actingAs($admin);

        Livewire::test(ActivityTeamsRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => ViewActivity::class,
        ])
            ->callAction(TestAction::make('give')->table(), [
                'team_id' => $team->getKey(),
                'set_as_default' => true,
            ]);

        $this->assertTrue($team->fresh()->defaultActivity->is($activity));
        $this->assertTrue($team->activities()->whereKey($existing->getKey())->exists());
    }

    public function test_admin_can_give_and_remove_a_venue_from_a_team(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $venue = Venue::factory()->create(['name' => 'Campsite']);
        $team = Team::factory()->create(['name' => 'Fox Coverts']);

        $this->actingAs($admin);

        Livewire::test(VenueTeamsRelationManager::class, [
            'ownerRecord' => $venue,
            'pageClass' => ViewVenue::class,
        ])
            ->callAction(TestAction::make('give')->table(), [
                'team_id' => $team->getKey(),
                'set_as_default' => true,
            ])
            ->assertDispatched('refresh-sidebar')
            ->assertDispatched('refresh-topbar')
            ->assertSee('Fox Coverts')
            ->assertSee('Default venue')
            ->assertSeeHtml(TeamsVenueResource::getUrl('index', panel: 'teams', tenant: $team));

        $this->assertTrue($team->fresh()->defaultVenue->is($venue));

        Livewire::test(VenueTeamsRelationManager::class, [
            'ownerRecord' => $venue,
            'pageClass' => ViewVenue::class,
        ])
            ->callAction(TestAction::make('remove')->table($team))
            ->assertDispatched('refresh-sidebar')
            ->assertDispatched('refresh-topbar');

        $this->assertFalse($team->fresh()->venues()->whereKey($venue->getKey())->exists());
        $this->assertNull($team->fresh()->default_venue_id);
    }

    public function test_admin_activity_list_shows_team_counts(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $team = Team::factory()->create();
        $team->addActivity($activity);

        $this->actingAs($admin);

        Livewire::test(ListActivities::class)
            ->assertCanSeeTableRecords([$activity])
            ->assertSee('1');
    }
}
