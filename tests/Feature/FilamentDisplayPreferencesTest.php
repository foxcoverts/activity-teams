<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Activities\Pages\CreateActivity;
use App\Filament\Manage\Resources\Activities\Pages\EditActivity;
use App\Filament\Manage\Resources\Activities\Pages\ListActivities;
use App\Filament\Manage\Resources\Activities\Pages\ViewActivity;
use App\Filament\Manage\Resources\Bookings\Pages\ListBookings as ManageListBookings;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking as ManageViewBooking;
use App\Filament\Manage\Resources\Users\Pages\ViewUser;
use App\Filament\Manage\Resources\Venues\Pages\CreateVenue;
use App\Filament\Manage\Resources\Venues\Pages\EditVenue;
use App\Filament\Manage\Resources\Venues\Pages\ListVenues;
use App\Filament\Manage\Resources\Venues\Pages\ViewVenue;
use App\Filament\Team\Resources\Bookings\Pages\ListBookings as TeamListBookings;
use App\Filament\Team\Resources\Bookings\Pages\ViewBooking as TeamViewBooking;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentDisplayPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_dates_and_times_are_rendered_without_seconds_in_both_panels(): void
    {
        $user = User::factory()->teamLeader()->create(['timezone' => 'Europe/London']);
        $team = $user->managedTeams()->firstOrFail();
        $booking = Booking::factory()->create([
            'team_id' => $team->getKey(),
            'start_at' => '2026-10-24 10:30:00',
            'end_at' => '2026-10-24 12:30:00',
            'timezone' => 'UTC',
            'status' => BookingStatus::Confirmed,
        ]);
        $tentativeBooking = Booking::factory()->create([
            'team_id' => $team->getKey(),
            'status' => BookingStatus::Tentative,
        ]);
        $cancelledBooking = Booking::factory()->cancelled()->create([
            'team_id' => $team->getKey(),
        ]);

        $this->travelTo('2026-10-20 12:00:00');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('manage'));
        Filament::setTenant($team);

        Livewire::test(ManageListBookings::class)
            ->assertSee('Oct 24, 2026 10:30')
            ->assertDontSee('Oct 24, 2026 10:30:00');
        Livewire::test(ManageViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Confirmed booking')
            ->assertSee('When')
            ->assertSee('Sat 24 Oct 2026, 11:30 to 13:30 (2 hours)')
            ->assertDontSee('Timezone')
            ->assertDontSee('Created at')
            ->assertDontSee('Updated at');
        Livewire::test(ManageViewBooking::class, ['record' => $tentativeBooking->getKey()])
            ->assertSee('Tentative booking');
        Livewire::test(ManageViewBooking::class, ['record' => $cancelledBooking->getKey()])
            ->assertSee('Cancelled booking');

        Filament::setCurrentPanel(Filament::getPanel('team'));
        Filament::setTenant($team);

        Livewire::test(TeamListBookings::class)
            ->assertSee('Oct 24, 2026 10:30')
            ->assertDontSee('Oct 24, 2026 10:30:00');
        Livewire::test(TeamViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Confirmed booking')
            ->assertSee('When')
            ->assertSee('Sat 24 Oct 2026, 11:30 to 13:30 (2 hours)')
            ->assertDontSee('Timezone')
            ->assertDontSee('Created at')
            ->assertDontSee('Updated at');
        Livewire::test(TeamViewBooking::class, ['record' => $tentativeBooking->getKey()])
            ->assertSee('Tentative booking');
        Livewire::test(TeamViewBooking::class, ['record' => $cancelledBooking->getKey()])
            ->assertSee('Cancelled booking');
    }

    public function test_booking_when_shows_both_local_dates_for_a_multi_day_booking(): void
    {
        $user = User::factory()->teamLeader()->create(['timezone' => 'Europe/London']);
        $team = $user->managedTeams()->firstOrFail();
        $booking = Booking::factory()->create([
            'team_id' => $team->getKey(),
            'start_at' => '2026-10-20 10:30:00',
            'end_at' => '2026-10-21 10:30:00',
            'timezone' => 'UTC',
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        Filament::setTenant($team);

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Tue 20 Oct 2026 11:30 to Wed 21 Oct 2026 11:30 (1 day)');

        Filament::setCurrentPanel(Filament::getPanel('team'));
        Filament::setTenant($team);

        Livewire::test(TeamViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Tue 20 Oct 2026 11:30 to Wed 21 Oct 2026 11:30 (1 day)');
    }

    public function test_lead_instructor_notes_are_visible_only_to_the_lead_and_team_managers(): void
    {
        $manager = User::factory()->teamLeader()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $lead = User::factory()->create();
        $member = User::factory()->create();
        $team->grant($lead, MembershipRole::Member);
        $team->grant($member, MembershipRole::Member);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $lead->getKey(),
            'lead_instructor_notes' => 'Private lead arrangements',
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        Filament::setTenant($team);

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Private lead arrangements');

        Filament::setCurrentPanel(Filament::getPanel('team'));
        Filament::setTenant($team);
        $this->actingAs($lead);

        Livewire::test(TeamViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Private lead arrangements');

        $this->actingAs($member);

        Livewire::test(TeamViewBooking::class, ['record' => $booking->getKey()])
            ->assertDontSee('Private lead arrangements');
    }

    public function test_catalog_lists_and_view_screens_hide_record_ids_and_slugs(): void
    {
        $user = User::factory()->teamLeader()->create();
        $activity = Activity::factory()->create([
            'slug' => 'hidden-activity-slug',
        ]);
        $venue = Venue::factory()->create([
            'slug' => 'hidden-venue-slug',
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        Filament::setTenant($user->managedTeams()->firstOrFail());

        Livewire::test(ListActivities::class)
            ->assertTableColumnDoesNotExist('id')
            ->assertTableColumnDoesNotExist('slug');
        Livewire::test(ListVenues::class)
            ->assertTableColumnDoesNotExist('id')
            ->assertTableColumnDoesNotExist('slug');

        Livewire::test(ViewActivity::class, ['record' => $activity->getKey()])
            ->assertDontSee('ID')
            ->assertDontSee('hidden-activity-slug');
        Livewire::test(ViewVenue::class, ['record' => $venue->getKey()])
            ->assertDontSee('ID')
            ->assertDontSee('hidden-venue-slug');
        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->assertDontSee('ID');
    }

    public function test_slugs_are_hidden_on_create_forms_and_visible_on_edit_forms(): void
    {
        $user = User::factory()->teamLeader()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        Filament::setTenant($user->managedTeams()->firstOrFail());

        Livewire::test(CreateActivity::class)
            ->assertFormFieldHidden('slug');
        Livewire::test(EditActivity::class, ['record' => $activity->getKey()])
            ->assertFormFieldVisible('slug');
        Livewire::test(CreateVenue::class)
            ->assertFormFieldHidden('slug');
        Livewire::test(EditVenue::class, ['record' => $venue->getKey()])
            ->assertFormFieldVisible('slug');
    }
}
