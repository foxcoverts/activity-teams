<?php

namespace Tests\Feature\Manage;

use App\Filament\Teams\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
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
}
