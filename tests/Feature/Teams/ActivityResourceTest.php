<?php

namespace Tests\Feature\Teams;

use App\Filament\Teams\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_the_activity_list_is_sorted_by_name(): void
    {
        $manager = User::factory()->teamManager()->create();
        $zebra = Activity::factory()->create(['name' => 'Zebra']);
        $archery = Activity::factory()->create(['name' => 'Archery']);
        $climbing = Activity::factory()->create(['name' => 'Climbing']);

        $this->actingAs($manager);

        Livewire::test(ListActivities::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }

    public function test_giving_an_activity_refreshes_navigation_chrome(): void
    {
        // Activity factory attaches to the latest team when one exists.
        $activity = Activity::factory()->create();
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();

        $this->actingAs($admin);
        Filament::setTenant($team);

        Livewire::test(ListActivities::class)
            ->callAction('give', data: [
                'activity_id' => $activity->getKey(),
            ])
            ->assertDispatched('refresh-sidebar')
            ->assertDispatched('refresh-topbar');

        $this->assertTrue($team->activities()->whereKey($activity->getKey())->exists());
    }

    public function test_removing_an_activity_refreshes_navigation_chrome(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $activity = Activity::factory()->create();
        $team->addActivity($activity);

        $this->actingAs($manager);

        Livewire::test(ListActivities::class)
            ->callTableAction('remove', $activity)
            ->assertDispatched('refresh-sidebar')
            ->assertDispatched('refresh-topbar');

        $this->assertFalse($team->fresh()->activities()->whereKey($activity->getKey())->exists());
    }
}
