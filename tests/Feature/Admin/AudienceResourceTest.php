<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Audiences\Pages\ListAudiences;
use App\Models\Audience;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AudienceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_audience_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $zebra = Audience::factory()->create(['name' => 'Zebra']);
        $archery = Audience::factory()->create(['name' => 'Archery']);
        $climbing = Audience::factory()->create(['name' => 'Climbing']);

        $this->actingAs($admin);

        Livewire::test(ListAudiences::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }
}
