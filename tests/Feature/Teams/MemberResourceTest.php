<?php

namespace Tests\Feature\Teams;

use App\Filament\Teams\Resources\Members\Pages\ListMembers;
use App\Models\User;
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
}
