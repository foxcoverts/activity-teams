<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Manage\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Manage\Resources\Bookings\Pages\EditBooking;
use App\Filament\Manage\Resources\Bookings\Pages\ListBookings;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Grid;
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
                'start_date' => '2026-10-24',
                'start_time' => '09:30',
                'end_time' => '11:30',
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
        $this->assertSame('2026-10-24 08:30', $booking->start_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-24 10:30', $booking->end_at->utc()->format('Y-m-d H:i'));
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
            ->assertSeeInOrder(['Start', 'End'])
            ->assertFormComponentExists('schedule_groups', fn (Grid $component): bool => $component->getColumns('default') === 1
                && $component->getColumns('sm') === 6)
            ->assertFormComponentExists('schedule_groups.start_schedule', fn (FusedGroup $component): bool => $component->getLabel() === 'Start'
                && ! $component->isLabelHidden()
                && $component->getColumnSpan('sm') === 3
                && isset(
                    $component->getChildSchema()?->getFlatFields(withHidden: true)['start_date'],
                    $component->getChildSchema()?->getFlatFields(withHidden: true)['start_time'],
                ))
            ->assertFormComponentExists('schedule_groups.end_schedule', fn (FusedGroup $component): bool => isset(
                $component->getChildSchema()?->getFlatFields(withHidden: true)['end_time'],
                $component->getChildSchema()?->getFlatFields(withHidden: true)['end_date'],
            ) && $component->getLabel() === 'End'
                && ! $component->isLabelHidden()
                && ! $this->isEndDateVisible($component)
                && $component->getColumns('sm') === 1
                && $component->getColumnSpan('sm') === 1)
            ->assertFormFieldExists('timezone', fn (TimezoneSelect $field): bool => $field->isSearchable()
                && preg_match('/^London \([+-]\d{2}:\d{2}\)$/', $field->getOptions()['Europe']['Europe/London'] ?? '') === 1);
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
                'start_date' => '2026-10-17',
                'start_time' => '09:30',
                'end_date' => '2026-10-17',
                'end_time' => '11:30',
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

    public function test_changing_the_start_keeps_the_booking_duration_and_earlier_end_time_rolls_over(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'UTC'));

        $user = User::factory()->teamLeader()->create([
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->set('data.start_time', '10:00')
            ->assertFormSet([
                'start_date' => '2026-10-17',
                'start_time' => '10:00',
                'end_date' => '2026-10-17',
                'end_time' => '12:00',
            ])
            ->set('data.end_time', '14:00')
            ->assertFormSet([
                'end_time' => '14:00',
            ])
            ->set('data.start_time', '09:00')
            ->assertFormSet([
                'end_time' => '13:00',
            ])
            ->set('data.end_time', '08:00')
            ->assertFormSet([
                'end_date' => '2026-10-18',
                'end_time' => '08:00',
            ])
            ->assertFormComponentExists('schedule_groups.end_schedule', fn (FusedGroup $component): bool => $this->isEndDateVisible($component)
                && $component->getColumns('sm') === 3
                && $component->getColumnSpan('sm') === 3)
            ->set('data.end_date', '2026-10-17')
            ->assertFormSet([
                'end_date' => '2026-10-17',
                'end_time' => '09:00',
            ])
            ->assertFormComponentExists('schedule_groups.end_schedule', fn (FusedGroup $component): bool => ! $this->isEndDateVisible($component))
            ->set('data.end_time', '08:00')
            ->assertFormSet(['end_date' => '2026-10-18'])
            ->set('data.end_time', '10:00')
            ->assertFormSet([
                'end_date' => '2026-10-17',
                'end_time' => '10:00',
            ])
            ->assertFormComponentExists('schedule_groups.end_schedule', fn (FusedGroup $component): bool => ! $this->isEndDateVisible($component)
                && $component->getColumns('sm') === 1
                && $component->getColumnSpan('sm') === 1);
    }

    public function test_creating_an_overnight_booking_saves_both_instants_in_utc(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'UTC'));

        $user = User::factory()->teamLeader()->create([
            'timezone' => 'America/New_York',
        ]);
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->id,
                'venue_id' => $venue->id,
                'group_name' => '1st Anytown Scouts',
                'start_date' => '2026-10-17',
                'start_time' => '22:00',
                'end_time' => '02:00',
                'timezone' => 'America/New_York',
            ])
            ->assertFormSet(['end_date' => '2026-10-18'])
            ->assertFormComponentExists('schedule_groups.end_schedule', fn (FusedGroup $component): bool => $this->isEndDateVisible($component))
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('2026-10-18 02:00', $booking->start_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-18 06:00', $booking->end_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('America/New_York', $booking->timezone->getName());
    }

    public function test_changing_timezone_preserves_the_schedule_instants(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'UTC'));

        $user = User::factory()->teamLeader()->create([
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->set('data.timezone', 'Europe/London')
            ->assertFormSet([
                'start_date' => '2026-10-17',
                'start_time' => '14:30',
                'end_date' => '2026-10-17',
                'end_time' => '16:30',
            ]);
    }

    public function test_an_overnight_booking_across_a_dst_change_uses_local_wall_times(): void
    {
        $user = User::factory()->teamLeader()->create([
            'timezone' => 'America/New_York',
        ]);
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->id,
                'venue_id' => $venue->id,
                'group_name' => '1st Anytown Scouts',
                'start_date' => '2026-10-31',
                'start_time' => '23:00',
                'end_time' => '02:00',
                'timezone' => 'America/New_York',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('2026-11-01 03:00', $booking->start_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-11-01 07:00', $booking->end_at->utc()->format('Y-m-d H:i'));
    }

    public function test_table_edit_action_hydrates_and_saves_the_local_schedule(): void
    {
        $user = User::factory()->teamLeader()->create();
        $booking = Booking::factory()->create([
            'start_at' => Carbon::parse('2026-10-20 13:00:00', 'UTC'),
            'end_at' => Carbon::parse('2026-10-20 16:00:00', 'UTC'),
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($user);

        Livewire::test(ListBookings::class)
            ->callAction(TestAction::make('edit')->table($booking), [
                'start_time' => '10:00',
            ])
            ->assertHasNoFormErrors();

        $booking->refresh();
        $this->assertSame('2026-10-20 14:00', $booking->start_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-20 17:00', $booking->end_at->utc()->format('Y-m-d H:i'));
    }

    public function test_list_create_action_combines_the_schedule_fields_before_saving(): void
    {
        $user = User::factory()->teamLeader()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($user);

        Livewire::test(ListBookings::class)
            ->callAction('create', data: [
                'activity_id' => $activity->id,
                'venue_id' => $venue->id,
                'group_name' => '1st Anytown Scouts',
                'start_date' => '2026-10-24',
                'start_time' => '09:30',
                'end_time' => '11:30',
                'timezone' => 'Europe/London',
            ])
            ->assertHasNoFormErrors();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('2026-10-24 08:30', $booking->start_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-24 10:30', $booking->end_at->utc()->format('Y-m-d H:i'));
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
                'start_date' => '2026-10-20',
                'start_time' => '09:00',
                'end_date' => '2026-10-20',
                'end_time' => '12:00',
            ])
            ->assertFormComponentExists('schedule_groups.end_schedule', fn (FusedGroup $component): bool => $this->isEndDateVisible($component))
            ->set('data.start_time', '10:00')
            ->assertFormSet([
                'end_time' => '13:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $booking->refresh();
        $this->assertSame('2026-10-20 14:00', $booking->start_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-10-20 17:00', $booking->end_at->utc()->format('Y-m-d H:i'));
    }

    public function test_team_member_cannot_create_a_booking(): void
    {
        $user = User::factory()->teamMember()->create();

        $this->actingAs($user)
            ->get('/manage/'.$user->teams()->first()->getKey().'/bookings/create')
            ->assertForbidden();
    }

    private function isEndDateVisible(FusedGroup $group): bool
    {
        $field = $group->getChildSchema()?->getFlatFields(withHidden: true)['end_date'] ?? null;

        return $field instanceof DatePicker && $field->isVisible();
    }
}
