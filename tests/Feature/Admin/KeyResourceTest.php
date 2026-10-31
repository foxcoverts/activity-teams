<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Keys\Pages\ListKeys;
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

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_key_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $zebra = Key::factory()->create(['name' => 'Zebra']);
        $archery = Key::factory()->create(['name' => 'Archery']);
        $climbing = Key::factory()->create(['name' => 'Climbing']);

        $this->actingAs($admin);

        Livewire::test(ListKeys::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }
}
