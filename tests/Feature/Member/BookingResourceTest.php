<?php

namespace Tests\Feature\Member;

use App\Enums\BookingAttendeeStatus;
use App\Filament\Member\Resources\Bookings\Pages\ListBookings;
use App\Filament\Member\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Member\Resources\Bookings\RelationManagers\AttendeesRelationManager;
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

    public function test_lead_instructor_notes_are_only_shown_to_the_lead_and_team_managers(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $manager = User::factory()->teamManager($team)->create();
        $booking = Booking::factory()->forTeam($team)->confirmed()->create(['lead_instructor_notes' => 'Bring the red rope']);

        $this->actingAs($member);
        Livewire::test(ViewBooking::class, ['record' => $booking->getKey()])
            ->assertDontSee('Lead instructor notes');

        $booking->update(['lead_instructor_id' => $member->getKey()]);
        Livewire::test(ViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Bring the red rope');

        $booking->update(['lead_instructor_id' => null]);
        $this->actingAs($manager);
        Livewire::test(ViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Lead instructor notes');
    }

    public function test_empty_notes_fields_are_hidden_on_the_booking_view(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $booking = Booking::factory()->forTeam($team)->confirmed()->create(['notes' => null, 'lead_instructor_notes' => null]);

        $this->actingAs($manager);
        Livewire::test(ViewBooking::class, ['record' => $booking->getKey()])
            ->assertDontSee('Lead instructor notes')
            ->assertDontSee('Notes');
    }

    public function test_the_guest_list_refreshes_when_attendance_changes(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->forTeam($member->teams()->firstOrFail())->confirmed()->create();
        $booking->attendees()->attach($member->getKey(), ['status' => BookingAttendeeStatus::NeedsAction->value]);

        $this->actingAs($member);
        $component = Livewire::test(AttendeesRelationManager::class, ['ownerRecord' => $booking, 'pageClass' => ViewBooking::class]);

        $booking->attendees()->updateExistingPivot($member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        $component->dispatch('booking-attendance-updated')->assertSee('Can help');
    }
}
