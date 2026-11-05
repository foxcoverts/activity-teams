<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Teams\Pages\ListTeams;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_the_team_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $zebra = Team::factory()->create(['name' => 'Zebra']);
        $archery = Team::factory()->create(['name' => 'Archery']);
        $climbing = Team::factory()->create(['name' => 'Climbing']);

        $this->actingAs($admin);

        Livewire::test(ListTeams::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }

    public function test_the_manager_filter_lists_only_active_managers(): void
    {
        $admin = User::factory()->systemAdmin()->create(['name' => 'Site Admin']);
        $team = Team::factory()->create();
        $active = User::factory()->teamManager($team)->create(['name' => 'Active Manager']);
        $suspended = User::factory()->teamManager($team)->create([
            'name' => 'Suspended Manager',
            'suspended' => true,
        ]);
        $member = User::factory()->teamMember($team)->create(['name' => 'Team Member']);
        $former = User::factory()->teamManager($team)->create(['name' => 'Former Manager']);
        $team->recordLeft($former);

        $this->actingAs($admin);

        $names = Livewire::test(ListTeams::class)
            ->instance()
            ->getTable()
            ->getFilter('managers.id')
            ->getRelationshipQuery()
            ->pluck('name');

        $this->assertTrue($names->contains($active->name));
        $this->assertFalse($names->contains($suspended->name));
        $this->assertFalse($names->contains($member->name));
        $this->assertFalse($names->contains($former->name));
        $this->assertFalse($names->contains($admin->name));
    }

    public function test_the_manager_filter_none_option_includes_teams_with_only_a_suspended_manager(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $open = Team::factory()->create(['name' => 'Open']);
        $suspendedOnly = Team::factory()->create(['name' => 'Suspended Only']);
        $covered = Team::factory()->create(['name' => 'Covered']);
        User::factory()->teamManager($suspendedOnly)->create(['suspended' => true]);
        User::factory()->teamManager($covered)->create();

        $this->actingAs($admin);

        Livewire::test(ListTeams::class)
            ->filterTable('managers.id', '__empty')
            ->assertCanSeeTableRecords([$open, $suspendedOnly])
            ->assertCanNotSeeTableRecords([$covered]);
    }
}
