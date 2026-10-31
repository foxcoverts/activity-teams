<?php

namespace Tests\Feature\Member;

use App\Filament\Member\Resources\KeyRings\Pages\ListKeyRings;
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

        Filament::setCurrentPanel(Filament::getPanel('member'));
    }

    public function test_the_key_ring_list_is_sorted_by_name(): void
    {
        $member = User::factory()->teamMember()->create();
        $zebra = KeyRing::factory()->heldBy($member)->create(['name' => 'Zebra']);
        $archery = KeyRing::factory()->heldBy($member)->create(['name' => 'Archery']);
        $climbing = KeyRing::factory()->heldBy($member)->create(['name' => 'Climbing']);

        $this->actingAs($member);

        Livewire::test(ListKeyRings::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }
}
