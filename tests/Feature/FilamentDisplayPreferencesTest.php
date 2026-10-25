<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Activities\Pages\CreateActivity;
use App\Filament\Admin\Resources\Activities\Pages\EditActivity;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\Activities\Pages\ViewActivity;
use App\Filament\Admin\Resources\Teams\Pages\CreateTeam;
use App\Filament\Admin\Resources\Teams\Pages\EditTeam;
use App\Filament\Admin\Resources\Teams\Pages\ListTeams;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Admin\Resources\Venues\Pages\CreateVenue;
use App\Filament\Admin\Resources\Venues\Pages\EditVenue;
use App\Filament\Admin\Resources\Venues\Pages\ListVenues;
use App\Filament\Admin\Resources\Venues\Pages\ViewVenue;
use App\Filament\Manage\Resources\Bookings\Pages\ListBookings as ManageListBookings;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking as ManageViewBooking;
use App\Filament\Member\Resources\Bookings\Pages\ListBookings as MemberListBookings;
use App\Filament\Member\Resources\Bookings\Pages\ViewBooking as MemberViewBooking;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Team;
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
        $user = User::factory()->teamManager()->create(['timezone' => 'Europe/London']);
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

        Filament::setCurrentPanel(Filament::getPanel('member'));
        Filament::setTenant($team);

        Livewire::test(MemberListBookings::class)
            ->assertSee('Oct 24, 2026 10:30')
            ->assertDontSee('Oct 24, 2026 10:30:00');
        Livewire::test(MemberViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Confirmed booking')
            ->assertSee('When')
            ->assertSee('Sat 24 Oct 2026, 11:30 to 13:30 (2 hours)')
            ->assertDontSee('Timezone')
            ->assertDontSee('Created at')
            ->assertDontSee('Updated at');
        Livewire::test(MemberViewBooking::class, ['record' => $tentativeBooking->getKey()])
            ->assertSee('Tentative booking');
        Livewire::test(MemberViewBooking::class, ['record' => $cancelledBooking->getKey()])
            ->assertSee('Cancelled booking');
    }

    public function test_booking_when_shows_both_local_dates_for_a_multi_day_booking(): void
    {
        $user = User::factory()->teamManager()->create(['timezone' => 'Europe/London']);
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

        Filament::setCurrentPanel(Filament::getPanel('member'));
        Filament::setTenant($team);

        Livewire::test(MemberViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Tue 20 Oct 2026 11:30 to Wed 21 Oct 2026 11:30 (1 day)');
    }

    public function test_lead_instructor_notes_are_visible_only_to_the_lead_and_team_managers(): void
    {
        $manager = User::factory()->teamManager()->create();
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

        Filament::setCurrentPanel(Filament::getPanel('member'));
        Filament::setTenant($team);
        $this->actingAs($lead);

        Livewire::test(MemberViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Private lead arrangements');

        $this->actingAs($member);

        Livewire::test(MemberViewBooking::class, ['record' => $booking->getKey()])
            ->assertDontSee('Private lead arrangements');
    }

    public function test_catalog_lists_and_view_screens_hide_record_ids_and_slugs(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $activity = Activity::factory()->create([
            'slug' => 'hidden-activity-slug',
        ]);
        $venue = Venue::factory()->create([
            'slug' => 'hidden-venue-slug',
        ]);
        Team::factory()->create([
            'name' => 'Hidden Team',
            'slug' => 'hidden-team-slug',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListActivities::class)
            ->assertTableColumnDoesNotExist('id')
            ->assertTableColumnDoesNotExist('slug');
        Livewire::test(ListVenues::class)
            ->assertTableColumnDoesNotExist('id')
            ->assertTableColumnDoesNotExist('slug');
        Livewire::test(ListTeams::class)
            ->assertTableColumnDoesNotExist('id')
            ->assertTableColumnDoesNotExist('slug')
            ->assertDontSee('hidden-team-slug');

        Livewire::test(ViewActivity::class, ['record' => $activity->getKey()])
            ->assertDontSee('ID')
            ->assertDontSee('hidden-activity-slug');
        Livewire::test(ViewVenue::class, ['record' => $venue->getKey()])
            ->assertDontSee('ID')
            ->assertDontSee('hidden-venue-slug');
        Livewire::test(ViewUser::class, ['record' => $admin->getKey()])
            ->assertDontSee('ID');
    }

    public function test_slugs_are_hidden_on_create_forms_and_visible_on_edit_forms(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $team = Team::factory()->create();

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateActivity::class)
            ->assertFormFieldHidden('slug');
        Livewire::test(EditActivity::class, ['record' => $activity->getKey()])
            ->assertFormFieldVisible('slug');
        Livewire::test(CreateVenue::class)
            ->assertFormFieldHidden('slug');
        Livewire::test(EditVenue::class, ['record' => $venue->getKey()])
            ->assertFormFieldVisible('slug');
        Livewire::test(CreateTeam::class)
            ->assertFormFieldHidden('slug');
        Livewire::test(EditTeam::class, ['record' => $team->getKey()])
            ->assertFormFieldVisible('slug');
    }

    public function test_slugs_must_be_url_safe(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $activity = Activity::factory()->create(['slug' => 'kept-activity']);
        $venue = Venue::factory()->create(['slug' => 'kept-venue']);
        $team = Team::factory()->create(['slug' => 'kept-team']);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditActivity::class, ['record' => $activity->getKey()])
            ->fillForm(['slug' => 'not/a slug'])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'alpha_dash']);
        Livewire::test(EditVenue::class, ['record' => $venue->getKey()])
            ->fillForm(['slug' => 'not/a slug'])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'alpha_dash']);
        Livewire::test(EditTeam::class, ['record' => $team->getKey()])
            ->fillForm(['slug' => 'not/a slug'])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'alpha_dash']);

        $this->assertDatabaseHas(Activity::class, [
            'id' => $activity->getKey(),
            'slug' => 'kept-activity',
        ]);
        $this->assertDatabaseHas(Venue::class, [
            'id' => $venue->getKey(),
            'slug' => 'kept-venue',
        ]);
        $this->assertDatabaseHas(Team::class, [
            'id' => $team->getKey(),
            'slug' => 'kept-team',
        ]);
    }
}
