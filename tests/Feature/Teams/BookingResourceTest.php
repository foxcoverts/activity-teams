<?php

namespace Tests\Feature\Teams;

use App\Filament\Member\Resources\Bookings\BookingResource as MemberBookingResource;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Bookings\Pages\ListBookings;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Models\Booking;
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

        Filament::setCurrentPanel('teams');
    }

    public function test_the_booking_list_is_sorted_by_start_time(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $later = Booking::factory()->forTeam($team)->create([
            'start_at' => '2026-12-02 09:30:00',
            'end_at' => '2026-12-02 11:30:00',
        ]);
        $sooner = Booking::factory()->forTeam($team)->create([
            'start_at' => '2026-12-01 09:30:00',
            'end_at' => '2026-12-01 11:30:00',
        ]);

        $this->actingAs($manager);

        Livewire::test(ListBookings::class)
            ->assertSet('activeTab', 'upcoming')
            ->assertCanSeeTableRecords([$sooner, $later], inOrder: true)
            ->sortTable('start_at', 'desc')
            ->assertCanSeeTableRecords([$later, $sooner], inOrder: true);
    }

    public function test_the_upcoming_tab_hides_past_bookings_and_history_shows_only_past(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();

        $upcoming = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHours(2),
            'group_name' => 'Upcoming group',
        ]);
        $past = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->subDays(10),
            'end_at' => now()->subDays(10)->addHours(2),
            'group_name' => 'Past group',
        ]);

        $this->actingAs($manager);

        Livewire::test(ListBookings::class)
            ->assertSet('activeTab', 'upcoming')
            ->assertCanSeeTableRecords([$upcoming])
            ->assertCanNotSeeTableRecords([$past])
            ->set('activeTab', 'history')
            ->assertCanSeeTableRecords([$past])
            ->assertCanNotSeeTableRecords([$upcoming]);
    }

    public function test_a_manager_sees_respond_and_manage_actions_on_the_booking_view(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $booking = Booking::factory()->forTeam($team)->confirmed()->create();
        $booking->attendees()->attach($manager->getKey(), ['status' => 'needs-action']);

        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('respondYes')
            ->assertActionVisible('edit')
            ->assertActionHidden('delete')
            ->assertSee(__('Manage'))
            ->assertSee(__('Awaiting response'));

        $cancelled = Booking::factory()->forTeam($team)->cancelled()->create();

        Livewire::test(ViewBooking::class, ['record' => $cancelled->getRouteKey()])
            ->assertSee(__('Manage'))
            ->assertActionVisible('restoreBooking')
            ->assertActionVisible('delete');
    }

    public function test_a_teams_booking_view_still_shows_the_public_summary_to_guests(): void
    {
        $booking = Booking::factory()->confirmed()->create([
            'group_name' => 'Secret Group Name',
        ]);

        $this->get(BookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $booking->team))
            ->assertOk()
            ->assertSee(__('Can you attend this event?'))
            ->assertSee(__('View full details'))
            ->assertDontSee('Secret Group Name')
            ->assertSessionHas('url.intended', MemberBookingResource::viewUrl($booking));
    }
}
