<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Audiences\AudienceResource;
use App\Filament\Admin\Resources\Audiences\Pages\EditAudience;
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

        Filament::setCurrentPanel('admin');
    }

    public function test_the_audience_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $zebra = Audience::factory()->create(['name' => 'Zebra']);
        $archery = Audience::factory()->create(['name' => 'Archery']);
        $climbing = Audience::factory()->create(['name' => 'Climbing']);

        $this->actingAs($admin);

        Livewire::test(ListAudiences::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true)
            ->sortTable('name', 'desc')
            ->assertCanSeeTableRecords([$zebra, $climbing, $archery], inOrder: true);
    }

    public function test_saving_an_audience_returns_to_the_view_page(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $audience = Audience::factory()->create(['name' => 'Scouts']);

        $this->actingAs($admin);

        Livewire::test(EditAudience::class, ['record' => $audience->getKey()])
            ->fillForm([
                'name' => 'Explorer Scouts',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(AudienceResource::getUrl('view', ['record' => $audience]));

        $this->assertSame('Explorer Scouts', $audience->fresh()->name);
    }
}
