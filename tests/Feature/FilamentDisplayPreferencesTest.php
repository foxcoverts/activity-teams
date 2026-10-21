<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
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
        $user = User::factory()->teamLeader()->create();
        $team = $user->managedTeams()->firstOrFail();
        $booking = Booking::factory()->create([
            'team_id' => $team->getKey(),
            'start_at' => '2026-10-24 10:30:00',
            'end_at' => '2026-10-24 12:30:00',
            'timezone' => 'UTC',
            'status' => BookingStatus::Confirmed,
        ]);

        $this->travelTo('2026-10-20 12:00:00');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('manage'));
        Filament::setTenant($team);

        Livewire::test(ManageListBookings::class)
            ->assertSee('Oct 24, 2026 10:30')
            ->assertDontSee('Oct 24, 2026 10:30:00');
        Livewire::test(ManageViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Oct 24, 2026 10:30')
            ->assertDontSee('Oct 24, 2026 10:30:00');

        Filament::setCurrentPanel(Filament::getPanel('team'));
        Filament::setTenant($team);

        Livewire::test(TeamListBookings::class)
            ->assertSee('Oct 24, 2026 10:30')
            ->assertDontSee('Oct 24, 2026 10:30:00');
        Livewire::test(TeamViewBooking::class, ['record' => $booking->getKey()])
            ->assertSee('Oct 24, 2026 10:30')
            ->assertDontSee('Oct 24, 2026 10:30:00');
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
