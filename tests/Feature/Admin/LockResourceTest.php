<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Locks\Pages\ListLocks;
use App\Models\Lock;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LockResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_the_key_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $zebra = Lock::factory()->create(['name' => 'Zebra']);
        $archery = Lock::factory()->create(['name' => 'Archery']);
        $climbing = Lock::factory()->create(['name' => 'Climbing']);

        $this->actingAs($admin);

        Livewire::test(ListLocks::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }
}
