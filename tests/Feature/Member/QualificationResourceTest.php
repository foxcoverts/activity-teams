<?php

namespace Tests\Feature\Member;

use App\Filament\Member\Resources\Qualifications\Pages\ListQualifications;
use App\Filament\Member\Resources\Qualifications\Pages\ViewQualification;
use App\Models\Qualification;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QualificationResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('member');
    }

    public function test_the_qualification_list_is_sorted_by_expiry(): void
    {
        $member = User::factory()->teamMember()->create();
        $later = Qualification::factory()->for($member)->create([
            'expires_on' => '2028-06-01',
        ]);
        $sooner = Qualification::factory()->for($member)->create([
            'expires_on' => '2027-01-01',
        ]);

        $this->actingAs($member);

        Livewire::test(ListQualifications::class)
            ->assertCanSeeTableRecords([$sooner, $later], inOrder: true)
            ->sortTable('expires_on', 'desc')
            ->assertCanSeeTableRecords([$later, $sooner], inOrder: true);
    }

    public function test_the_qualification_view_uses_a_sectioned_infolist(): void
    {
        $member = User::factory()->teamMember()->create();
        $permit = Qualification::factory()->for($member)->scoutPermit([
            'restrictions' => 'No night climbing',
        ])->create([
            'expires_on' => now()->addYear()->toDateString(),
        ]);

        $this->actingAs($member);

        Livewire::test(ViewQualification::class, ['record' => $permit->getKey()])
            ->assertSee('Qualification')
            ->assertSee('Type')
            ->assertSee('Expires')
            ->assertSee('Climbing and Abseiling')
            ->assertSee('Artificial Top Rope')
            ->assertSee('Leadership')
            ->assertSee('No night climbing');
    }
}
