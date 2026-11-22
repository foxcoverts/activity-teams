<?php

namespace Tests\Feature\Teams;

use App\Filament\Teams\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_give_options_omit_activities_without_audiences(): void
    {
        $broken = Activity::factory()->withoutAudiences()->create(['name' => 'Broken']);
        $ok = Activity::factory()->create(['name' => 'Climbing']);
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();

        $this->actingAs($admin);
        Filament::setTenant($team);

        $options = Activity::query()
            ->withAudiences()
            ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
            ->orderBy('name')
            ->pluck('name', 'id');

        $this->assertTrue($options->has($ok->getKey()));
        $this->assertFalse($options->has($broken->getKey()));

        Livewire::test(ListActivities::class)
            ->callAction('give', data: [
                'activity_id' => $ok->getKey(),
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue($team->activities()->whereKey($ok->getKey())->exists());
    }

    public function test_add_activity_rejects_activities_without_audiences(): void
    {
        $broken = Activity::factory()->withoutAudiences()->create(['name' => 'Broken']);
        $team = Team::factory()->create();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('Choose an activity that has an audience.'));

        $team->addActivity($broken);
    }

    public function test_activity_list_shows_none_when_there_are_no_audiences(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $activity = Activity::factory()->withoutAudiences()->create(['name' => 'Climbing']);
        $team->activities()->syncWithoutDetaching([$activity->getKey()]);

        $this->actingAs($manager);

        Livewire::test(ListActivities::class)
            ->assertCanSeeTableRecords([$activity])
            ->assertSee('None')
            ->assertDontSee('Set default audience');
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
