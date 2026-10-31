<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Venues\Pages\ListVenues;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VenueResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_venue_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $zebra = Venue::factory()->create(['name' => 'Zebra']);
        $archery = Venue::factory()->create(['name' => 'Archery']);
        $climbing = Venue::factory()->create(['name' => 'Climbing']);

        $this->actingAs($admin);

        Livewire::test(ListVenues::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }
}
