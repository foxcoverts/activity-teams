<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Manage\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Manage\Resources\Bookings\Pages\EditBooking;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AddBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('manage'));
    }

    public function test_team_leader_can_create_a_booking(): void
    {
        $user = User::factory()->teamLeader()->create();
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $venue = Venue::factory()->create(['name' => 'Fox Coverts Campsite']);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->id,
                'venue_id' => $venue->id,
                'start_at' => '2026-10-24 09:30:00',
                'end_at' => '2026-10-24 11:30:00',
                'timezone' => 'Europe/London',
                'group_name' => '1st Anytown Scouts',
                'notes' => 'x20 Young People',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertSame('1st Anytown Scouts', $booking->group_name);
        $this->assertTrue($booking->activity->is($activity));
        $this->assertTrue($booking->venue->is($venue));
    }

    public function test_create_booking_defaults_to_a_teams_only_activity_and_venue(): void
    {
        $user = User::factory()->teamLeader()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->assertFormSet([
                'activity_id' => $activity->getKey(),
                'venue_id' => $venue->getKey(),
            ])
            ->assertFormFieldExists('activity_id', fn (Select $field): bool => ! $field->canSelectPlaceholder())
            ->assertFormFieldExists('venue_id', fn (Select $field): bool => ! $field->canSelectPlaceholder())
            ->fillForm(['group_name' => '1st Anytown Scouts'])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->firstOrFail();
        $this->assertTrue($booking->activity->is($activity));
        $this->assertTrue($booking->venue->is($venue));
    }

    public function test_create_booking_defaults_each_field_when_multiple_options_exist(): void
    {
        $user = User::factory()->teamLeader()->create();
        $activity = Activity::factory()->create();
        Activity::factory()->create();
        Venue::factory()->create();
        $team = $user->managedTeams()->firstOrFail();

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->assertFormSet([
                'activity_id' => $activity->getKey(),
                'venue_id' => $team->default_venue_id,
            ]);
    }

    public function test_create_booking_uses_the_teams_explicit_activity_and_venue_defaults(): void
    {
        $user = User::factory()->teamLeader()->create();
        $team = $user->managedTeams()->firstOrFail();
        Activity::factory()->create(['name' => 'Climbing']);
        $defaultActivity = Activity::factory()->create(['name' => 'Archery']);
        Venue::factory()->create(['name' => 'Campsite']);
        $defaultVenue = Venue::factory()->create(['name' => 'Headquarters']);
        $team->setDefaultActivity($defaultActivity);
        $team->setDefaultVenue($defaultVenue);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->assertFormSet([
                'activity_id' => $defaultActivity->getKey(),
                'venue_id' => $defaultVenue->getKey(),
            ]);
    }

    public function test_create_booking_does_not_use_choices_from_another_team(): void
    {
        $user = User::factory()->teamLeader()->create();
        Activity::factory()->create();
        Venue::factory()->create();
        $otherTeam = Team::factory()->create();
        $otherTeam->grant($user, MembershipRole::Manager);

        $this->actingAs($user);
        Filament::setTenant($otherTeam);

        Livewire::test(CreateBooking::class)
            ->assertFormSet([
                'activity_id' => null,
                'venue_id' => null,
            ]);
    }

    public function test_the_booking_form_cannot_create_an_activity_or_venue(): void
    {
        $user = User::factory()->teamLeader()->create();

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->assertFormComponentActionDoesNotExist('activity_id', 'createOption')
            ->assertFormComponentActionDoesNotExist('venue_id', 'createOption');
    }

    public function test_booking_form_places_a_searchable_timezone_select_with_grouped_utc_offsets(): void
    {
        $user = User::factory()->teamLeader()->create();

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->assertSeeInOrder(['Start at', 'End at', 'Timezone', 'Venue'])
            ->assertFormFieldExists('timezone', fn (TimezoneSelect $field): bool => $field->isSearchable()
                && preg_match('/^Europe\/London \([+-]\d{2}:\d{2}\)$/', $field->getOptions()['Europe']['Europe/London'] ?? '') === 1);
    }

    public function test_create_booking_defaults_to_saturday_morning_in_the_users_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'UTC'));

        $user = User::factory()->teamLeader()->create([
            'timezone' => 'America/New_York',
        ]);
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->assertFormSet([
                'timezone' => 'America/New_York',
                'start_at' => '2026-10-17 13:30',
                'end_at' => '2026-10-17 15:30',
            ])
            ->fillForm([
                'activity_id' => $activity->id,
                'venue_id' => $venue->id,
                'group_name' => '1st Anytown Scouts',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame('2026-10-17 13:30', $booking->start_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-17 15:30', $booking->end_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('America/New_York', $booking->timezone->getName());
    }

    public function test_changing_the_start_keeps_the_booking_duration(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'UTC'));

        $user = User::factory()->teamLeader()->create([
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->set('data.start_at', '2026-10-18 10:00:00')
            ->assertFormSet([
                'start_at' => '2026-10-18 14:00',
                'end_at' => '2026-10-18 16:00',
            ])
            ->set('data.end_at', '2026-10-18 14:00:00')
            ->assertFormSet([
                'end_at' => '2026-10-18 18:00',
            ])
            ->set('data.start_at', '2026-10-18 09:00:00')
            ->assertFormSet([
                'end_at' => '2026-10-18 17:00',
            ])
            ->set('data.end_at', '2026-10-18 08:00:00')
            ->assertFormSet([
                'end_at' => '2026-10-18 13:00',
            ]);
    }

    public function test_editing_a_booking_keeps_its_existing_duration(): void
    {
        $user = User::factory()->teamLeader()->create([
            'timezone' => 'America/New_York',
        ]);
        $booking = Booking::factory()->create([
            'start_at' => Carbon::parse('2026-10-20 13:00:00', 'UTC'),
            'end_at' => Carbon::parse('2026-10-20 16:00:00', 'UTC'),
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($user);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->assertFormSet([
                'start_at' => '2026-10-20 13:00',
                'end_at' => '2026-10-20 16:00',
            ])
            ->set('data.start_at', '2026-10-20 10:00:00')
            ->assertFormSet([
                'end_at' => '2026-10-20 17:00',
            ]);
    }

    public function test_team_member_cannot_create_a_booking(): void
    {
        $user = User::factory()->teamMember()->create();

        $this->actingAs($user)
            ->get('/manage/'.$user->teams()->first()->getKey().'/bookings/create')
            ->assertForbidden();
    }
}
