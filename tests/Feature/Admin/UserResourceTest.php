<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_user_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create(['name' => 'Casey Admin']);
        $zebra = User::factory()->create(['name' => 'Zebra']);
        $alex = User::factory()->create(['name' => 'Alex']);

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$alex, $admin, $zebra], inOrder: true);
    }
}
