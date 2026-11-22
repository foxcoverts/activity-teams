<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Keys\Pages\CreateKey;
use App\Filament\Admin\Resources\Keys\Pages\EditKey;
use App\Filament\Admin\Resources\Keys\Pages\ListKeys;
use App\Filament\Admin\Resources\Keys\Pages\ViewKey;
use App\Filament\Admin\Resources\Locks\LockResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Key;
use App\Models\User;
use App\Support\Avatars;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KeyResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_the_keys_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $zebra = Key::factory()->create(['name' => 'Zebra']);
        $archery = Key::factory()->create(['name' => 'Archery']);
        $climbing = Key::factory()->create(['name' => 'Climbing']);

        $this->actingAs($admin);

        Livewire::test(ListKeys::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }

    public function test_the_keys_list_links_to_locks(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        $this->actingAs($admin);

        Livewire::test(ListKeys::class)
            ->assertActionVisible('locks')
            ->assertActionHasUrl('locks', LockResource::getUrl(panel: 'admin'));
    }

    public function test_the_keys_list_links_to_the_holder(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $user = User::factory()->create();
        Key::factory()->heldBy($user)->create();

        $this->actingAs($admin);

        $userUrl = UserResource::getUrl('view', ['record' => $user], panel: 'admin');

        Livewire::test(ListKeys::class)
            ->assertSuccessful()
            ->assertSeeText($user->name)
            ->assertSeeHtml('href="'.$userUrl.'"');
    }

    public function test_the_view_key_links_to_the_holder(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $user = User::factory()->create();
        $key = Key::factory()->heldBy($user)->create();

        $this->actingAs($admin);

        $userUrl = UserResource::getUrl('view', ['record' => $user], panel: 'admin');

        Livewire::test(ViewKey::class, ['record' => $key->getKey()])
            ->assertSuccessful()
            ->assertSeeText($user->name)
            ->assertSeeHtml('href="'.$userUrl.'"');
    }

    public function test_the_key_holder_select_shows_person_avatars(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $holder = User::factory()->create(['name' => 'Casey Holder']);
        $key = Key::factory()->heldBy($holder)->create();
        $avatarUrl = Avatars::userUrl($holder, 64);

        $this->actingAs($admin);

        $assertPersonOption = function (Select $select) use ($holder, $avatarUrl): bool {
            $label = $select->getOptionLabelFromRecord($holder);

            return is_string($label)
                && str_contains($label, 'Casey Holder')
                && str_contains($label, e($avatarUrl));
        };

        Livewire::test(CreateKey::class)
            ->assertSchemaComponentExists('holder_id', checkComponentUsing: $assertPersonOption);

        Livewire::test(EditKey::class, ['record' => $key->getKey()])
            ->assertSchemaComponentExists('holder_id', checkComponentUsing: $assertPersonOption);
    }
}
