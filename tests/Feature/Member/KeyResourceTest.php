<?php

namespace Tests\Feature\Member;

use App\Filament\Member\Resources\Keys\Pages\ListKeys;
use App\Models\Key;
use App\Models\User;
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

        Filament::setCurrentPanel('member');
    }

    public function test_the_key_ring_list_is_sorted_by_name(): void
    {
        $member = User::factory()->teamMember()->create();
        $zebra = Key::factory()->heldBy($member)->create(['name' => 'Zebra']);
        $archery = Key::factory()->heldBy($member)->create(['name' => 'Archery']);
        $climbing = Key::factory()->heldBy($member)->create(['name' => 'Climbing']);

        $this->actingAs($member);

        Livewire::test(ListKeys::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }
}
