<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\KeyRings\Pages\ListKeyRings;
use App\Models\KeyRing;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KeyRingResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_key_ring_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $zebra = KeyRing::factory()->create(['name' => 'Zebra']);
        $archery = KeyRing::factory()->create(['name' => 'Archery']);
        $climbing = KeyRing::factory()->create(['name' => 'Climbing']);

        $this->actingAs($admin);

        Livewire::test(ListKeyRings::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }
}
