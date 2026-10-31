<?php

namespace Tests\Feature\Manage;

use App\Filament\Manage\Resources\Keys\Pages\ListKeys;
use App\Models\Activity;
use App\Models\Lock;
use App\Models\Key;
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

        Filament::setCurrentPanel(Filament::getPanel('manage'));
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
}
