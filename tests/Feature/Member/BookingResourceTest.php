<?php

namespace Tests\Feature\Member;

use App\Filament\Member\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('member'));
    }

    public function test_the_booking_list_is_sorted_by_start_time(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $later = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => '2026-12-02 09:30:00',
            'end_at' => '2026-12-02 11:30:00',
        ]);
        $sooner = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => '2026-12-01 09:30:00',
            'end_at' => '2026-12-01 11:30:00',
        ]);

        $this->actingAs($member);

        Livewire::test(ListBookings::class)
            ->assertCanSeeTableRecords([$sooner, $later], inOrder: true);
    }

    public function test_the_team_filter_only_offers_the_members_teams_and_filters_bookings(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $otherTeam = Team::factory()->create();
        $mine = Booking::factory()->forTeam($team)->confirmed()->create();

        $this->actingAs($member);

        Livewire::test(ListBookings::class)
            ->assertTableFilterExists('team_id', fn ($filter): bool => array_keys($filter->getOptions()) === [$team->getKey()])
            ->filterTable('team_id', $team->getKey())
            ->assertCanSeeTableRecords([$mine]);

        $this->assertNotContains($otherTeam->getKey(), array_keys($member->teams()->pluck('teams.name', 'teams.id')->all()));
    }
}
