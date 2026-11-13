<?php

namespace Tests\Feature;

use App\Filament\Teams\Resources\BookingDebriefs\BookingDebriefResource;
use App\Filament\Teams\Resources\BookingDebriefs\Pages\ListBookingDebriefs;
use App\Filament\Teams\Resources\BookingDebriefs\Pages\ViewBookingDebrief;
use App\Models\Booking;
use App\Models\BookingDebrief;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingDebriefResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_debriefs_nav_is_hidden_without_feature(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        assert($team instanceof Team);

        $this->actingAs($manager);
        Filament::setTenant($team);

        $this->assertFalse(BookingDebriefResource::canViewAny());
        $this->assertFalse(BookingDebriefResource::shouldRegisterNavigation());
    }

    public function test_team_members_can_list_and_view_debriefs_when_feature_enabled(): void
    {
        $team = Team::factory()->withDebrief()->create();
        $member = User::factory()->teamMember($team)->create();
        $lead = User::factory()->teamMember($team)->create();
        $booking = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->subDays(2)->setTime(9, 0),
            'end_at' => now()->subDays(2)->setTime(11, 0),
            'lead_instructor_id' => $lead->getKey(),
        ]);
        $debrief = BookingDebrief::factory()->create([
            'booking_id' => $booking->getKey(),
            'hours' => 2,
            'participant_count' => 6,
            'weather' => 'Clear',
            'notes' => 'Steady session',
            'completed_by_id' => $lead->getKey(),
        ]);

        $otherTeam = Team::factory()->withDebrief()->create();
        $otherBooking = Booking::factory()->forTeam($otherTeam)->confirmed()->create([
            'start_at' => now()->subDay()->setTime(9, 0),
            'end_at' => now()->subDay()->setTime(11, 0),
        ]);
        $otherDebrief = BookingDebrief::factory()->create([
            'booking_id' => $otherBooking->getKey(),
            'notes' => 'Other team only',
        ]);

        $this->actingAs($member);
        Filament::setTenant($team);

        $this->assertTrue(BookingDebriefResource::canViewAny());
        $this->assertTrue(BookingDebriefResource::shouldRegisterNavigation());

        Livewire::test(ListBookingDebriefs::class)
            ->assertCanSeeTableRecords([$debrief])
            ->assertCanNotSeeTableRecords([$otherDebrief]);

        Livewire::test(ViewBookingDebrief::class, ['record' => $debrief->getRouteKey()])
            ->assertSuccessful()
            ->assertSeeText(__('Operating hours'))
            ->assertSeeText('Steady session')
            ->assertSeeText($booking->activityTitle());

        $this->get(BookingDebriefResource::getUrl('view', ['record' => $otherDebrief], tenant: $team))
            ->assertNotFound();
    }

    public function test_system_admin_can_open_debriefs_without_membership(): void
    {
        $team = Team::factory()->withDebrief()->create();
        $admin = User::factory()->systemAdmin()->create();
        $booking = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->subDays(2)->setTime(9, 0),
            'end_at' => now()->subDays(2)->setTime(11, 0),
        ]);
        BookingDebrief::factory()->create([
            'booking_id' => $booking->getKey(),
            'participant_count' => 3,
        ]);

        $this->assertFalse($admin->isCurrentMemberOf($team));

        $this->actingAs($admin);
        Filament::setTenant($team);

        $this->assertTrue(BookingDebriefResource::canViewAny());

        Livewire::test(ListBookingDebriefs::class)
            ->assertSuccessful();
    }
}
