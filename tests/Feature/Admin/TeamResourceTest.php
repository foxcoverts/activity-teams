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

        Filament::setCurrentPanel(Filament::getPanel('admin'));
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
}
