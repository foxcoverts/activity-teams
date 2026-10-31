<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Audience;
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

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_activity_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $zebra = Activity::factory()->create(['name' => 'Zebra']);
        $archery = Activity::factory()->create(['name' => 'Archery']);
        $climbing = Activity::factory()->create(['name' => 'Climbing']);

        $this->actingAs($admin);

        Livewire::test(ListActivities::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }

    public function test_the_activity_list_shows_audiences_and_an_empty_placeholder(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $climbing = Activity::factory()->create(['name' => 'Climbing']);
        Activity::factory()->create(['name' => 'Archery']);
        ActivityAudience::factory()->for($climbing)->for(Audience::factory()->create(['name' => 'Scouts']))->create();
        ActivityAudience::factory()->for($climbing)->for(Audience::factory()->create(['name' => 'Guides']))->create();

        $this->actingAs($admin);

        Livewire::test(ListActivities::class)
            ->assertSee('Scouts')
            ->assertSee('Guides')
            ->assertSee('No audiences');
    }
}
