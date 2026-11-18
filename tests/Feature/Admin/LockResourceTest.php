<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Keys\KeyResource;
use App\Filament\Admin\Resources\Locks\LockResource;
use App\Filament\Admin\Resources\Locks\Pages\ListLocks;
use App\Filament\Admin\Resources\Locks\Pages\ViewLock;
use App\Filament\Admin\Resources\Locks\RelationManagers\CoversRelationManager;
use App\Filament\Admin\Resources\Locks\RelationManagers\KeysRelationManager;
use App\Models\Key;
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

    public function test_the_locks_list_links_to_keys(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        $this->actingAs($admin);

        Livewire::test(ListLocks::class)
            ->assertActionVisible('keys')
            ->assertActionHasUrl('keys', KeyResource::getUrl(panel: 'admin'));
    }

    public function test_view_defaults_relation_tab_to_keys_when_only_keys_are_attached(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $lock = Lock::factory()->create();
        Key::factory()->create()->locks()->attach($lock);

        $this->actingAs($admin);

        Livewire::test(ViewLock::class, ['record' => $lock->getKey()])
            ->assertSet('activeRelationManager', $this->relationManagerKey(KeysRelationManager::class));
    }

    public function test_view_defaults_relation_tab_to_covers_when_covers_are_attached(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $lock = Lock::factory()->create();
        $covered = Lock::factory()->create();
        $lock->covers()->attach($covered);
        Key::factory()->create()->locks()->attach($lock);

        $this->actingAs($admin);

        Livewire::test(ViewLock::class, ['record' => $lock->getKey()])
            ->assertSet('activeRelationManager', $this->relationManagerKey(CoversRelationManager::class));
    }

    /**
     * @param  class-string  $manager
     */
    private function relationManagerKey(string $manager): int|string
    {
        $key = array_search($manager, LockResource::getRelations(), true);

        $this->assertNotFalse($key);

        return $key;
    }
}
