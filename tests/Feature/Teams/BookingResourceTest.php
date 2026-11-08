<?php

namespace Tests\Feature\Teams;

use App\Filament\Member\Resources\Bookings\BookingResource as MemberBookingResource;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Bookings\Pages\ListBookings;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Models\Booking;
use App\Models\Lock;
use App\Models\User;
use App\Support\Palette;
use Filament\Actions\ActionGroup;
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

    public function test_the_history_tab_lists_past_bookings_newest_first(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();

        $older = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->subDays(20),
            'end_at' => now()->subDays(20)->addHours(2),
        ]);
        $newer = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->subDays(5),
            'end_at' => now()->subDays(5)->addHours(2),
        ]);

        $this->actingAs($manager);

        Livewire::test(ListBookings::class)
            ->set('activeTab', 'history')
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
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

    public function test_a_past_booking_shows_an_ended_badge_and_mutes_heading_and_header_actions(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $upcoming = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(2),
        ]);
        $past = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDays(2)->addHours(2),
        ]);
        $store = Lock::factory()->create(['name' => 'Climbing store']);
        foreach ([$upcoming, $past] as $booking) {
            $store->requirements()->firstOrCreate([
                'activity_id' => $booking->activity_id,
                'venue_id' => $booking->venue_id,
            ]);
        }

        $this->actingAs($manager);

        $positive = Palette::positive()[600];
        $neutral = Palette::neutral();

        $upcomingPage = Livewire::test(ViewBooking::class, ['record' => $upcoming->getRouteKey()])
            ->assertSee('Confirmed booking')
            ->assertDontSee('Ended')
            ->assertSeeHtml($positive)
            ->assertActionVisible('keys');

        $this->assertSame(
            Palette::uncertain(),
            $upcomingPage->instance()->getAction('keys')->getColor(),
        );

        $pastPage = Livewire::test(ViewBooking::class, ['record' => $past->getRouteKey()])
            ->assertSee('Confirmed booking')
            ->assertSee('Ended')
            ->assertSeeHtml($neutral[600])
            ->assertActionVisible('keys')
            ->assertSee(__('Manage'));

        $this->assertSame($neutral, $pastPage->instance()->getAction('keys')->getColor());

        $manage = collect($pastPage->instance()->getCachedHeaderActions())
            ->first(fn (mixed $action): bool => $action instanceof ActionGroup
                && $action->getLabel() === __('Manage'));

        $this->assertNotNull($manage);
        $this->assertSame($neutral, $manage->getColor());

        $guests = Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $past,
            'pageClass' => ViewBooking::class,
        ]);

        $people = collect($guests->instance()->getTable()->getHeaderActions())
            ->first(fn (mixed $action): bool => $action instanceof ActionGroup
                && $action->getLabel() === __('People'));

        $this->assertNotNull($people);
        $this->assertSame($neutral, $people->getColor());
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
