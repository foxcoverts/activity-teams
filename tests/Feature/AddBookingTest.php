<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Events\BookingChanged;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Teams\Resources\Bookings\Pages\EditBooking;
use App\Filament\Teams\Resources\Bookings\Pages\ListBookings;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Booking;
use App\Models\BookingAttendance;
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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AddBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_team_leader_can_create_a_booking(): void
    {
        $user = User::factory()->teamManager()->create();
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $venue = Venue::factory()->create(['name' => 'Fox Coverts Campsite']);
        $audienceId = $this->offerAudience($activity);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->id,
                'audience_id' => $audienceId,
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
        $user = User::factory()->teamManager()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $audienceId = $this->offerAudience($activity);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->assertFormSet([
                'activity_id' => $activity->getKey(),
                'audience_id' => $audienceId,
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

    public function test_manager_can_book_an_other_activity_at_a_private_venue_without_catalog_venues(): void
    {
        Mail::fake();
        Queue::fake();

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        Activity::factory()->create();

        $this->actingAs($manager);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => 'other',
                'activity_label' => 'Planning meeting',
                'venue_id' => 'other',
                'name' => 'A home address',
                'street' => '1 Example Road',
                'city' => 'Anytown',
                'postcode' => 'AB1 2CD',
                'start_date' => '2026-10-24',
                'start_time' => '09:30',
                'end_time' => '11:30',
                'timezone' => 'Europe/London',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->firstOrFail();
        $privateVenue = Venue::onlyPrivate()
            ->where('booking_id', $booking->getKey())
            ->firstOrFail();

        $this->assertNull($booking->activity_id);
        $this->assertNull($booking->audience_id);
        $this->assertSame('Planning meeting', $booking->activityTitle());
        $this->assertSame($privateVenue->getKey(), $booking->venue_id);
        $this->assertSame('A home address', $privateVenue->name);
        $this->assertFalse($team->venues()->whereKey($privateVenue->getKey())->exists());
        $this->assertSame(0, Venue::query()->count());

        $this->get(BookingResource::getUrl('view', ['record' => $booking], panel: 'teams'))
            ->assertOk()
            ->assertSee('A home address')
            ->assertSee('1 Example Road')
            ->assertSee('AB1 2CD');

        $this->get(route('booking.preview', $booking))
            ->assertOk()
            ->assertSee('A home address')
            ->assertDontSee('1 Example Road')
            ->assertDontSee('AB1 2CD');

        $lead = User::factory()->create();
        $booking->attendees()->attach($lead->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('confirmBooking', data: ['lead_instructor_id' => $lead->getKey()])
            ->assertHasNoFormErrors();

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame($lead->getKey(), $booking->lead_instructor_id);
        $this->assertSame($privateVenue->getKey(), $booking->venue_id);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('cancelBooking', data: ['reason' => 'Weather'])
            ->assertHasNoFormErrors();

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('restoreBooking')
            ->assertHasNoFormErrors();

        $booking->refresh();
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertSame($privateVenue->getKey(), $booking->venue_id);

        $booking->delete();
        $this->assertDatabaseMissing('venues', ['id' => $privateVenue->getKey()]);
    }

    public function test_private_venue_names_can_repeat_across_bookings(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        Activity::factory()->create();

        $this->actingAs($manager);

        foreach (['24', '25'] as $day) {
            Livewire::test(CreateBooking::class)
                ->fillForm([
                    'activity_id' => 'other',
                    'activity_label' => 'Planning meeting',
                    'venue_id' => 'other',
                    'name' => 'Home',
                    'start_date' => "2026-10-{$day}",
                    'start_time' => '09:30',
                    'end_time' => '11:30',
                    'timezone' => 'Europe/London',
                ])
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $this->assertSame(2, Venue::onlyPrivate()->where('name', 'Home')->count());
        $this->assertSame(0, Venue::query()->where('name', 'Home')->count());
    }

    public function test_activity_choice_and_custom_label_changes_increment_booking_sequence(): void
    {
        $booking = Booking::factory()->create();
        $catalogActivityId = (string) $booking->activity_id;
        $catalogAudienceId = (string) $booking->audience_id;
        $sequence = $booking->sequence;

        $booking->activity_id = null;
        $booking->audience_id = null;
        $booking->activity_label = 'Tower Maintenance';
        $booking->save();

        $this->assertSame($sequence + 1, $booking->sequence);
        $this->assertSame('Tower Maintenance', $booking->activityTitle());

        $booking->activity_label = 'Equipment Maintenance';
        $booking->save();

        $this->assertSame($sequence + 2, $booking->sequence);

        $booking->activity_id = $catalogActivityId;
        $booking->audience_id = $catalogAudienceId;
        $booking->activity_label = null;
        $booking->save();

        $this->assertSame($sequence + 3, $booking->sequence);
    }

    public function test_activity_name_field_is_hidden_until_used_and_visible_for_an_existing_label(): void
    {
        $manager = User::factory()->teamManager()->create();
        Activity::factory()->create(['name' => 'Climbing']);
        Venue::factory()->create();

        $this->actingAs($manager);

        Livewire::test(CreateBooking::class)
            ->assertFormFieldHidden('activity_label')
            ->assertFormComponentActionVisible('activity_id', 'showAs')
            ->callFormComponentAction('activity_id', 'showAs')
            ->assertFormFieldVisible('activity_label')
            ->assertSeeText('Activity name')
            ->assertSee('placeholder="Climbing"', false);

        $booking = Booking::factory()->create([
            'activity_label' => 'Tower Maintenance',
        ]);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->assertFormFieldVisible('activity_label');
    }

    public function test_catalog_activity_that_requires_a_label_always_shows_required_activity_name(): void
    {
        $manager = User::factory()->teamManager()->create();
        $activity = Activity::factory()->requiresActivityLabel()->create(['name' => 'Climbing Access']);
        $venue = Venue::factory()->create();
        $audienceId = $this->offerAudience($activity);

        $this->actingAs($manager);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->getKey(),
            ])
            ->assertFormFieldVisible('activity_label')
            ->assertFormComponentActionHidden('activity_id', 'showAs')
            ->fillForm([
                'activity_id' => $activity->getKey(),
                'audience_id' => $audienceId,
                'venue_id' => $venue->getKey(),
                'start_date' => '2026-10-24',
                'start_time' => '09:30',
                'end_time' => '11:30',
                'timezone' => 'Europe/London',
            ])
            ->call('create')
            ->assertHasFormErrors(['activity_label']);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->getKey(),
                'audience_id' => $audienceId,
                'activity_label' => 'Tower Inspection',
                'venue_id' => $venue->getKey(),
                'start_date' => '2026-10-24',
                'start_time' => '09:30',
                'end_time' => '11:30',
                'timezone' => 'Europe/London',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->firstOrFail();

        $this->assertTrue($booking->activity->is($activity));
        $this->assertSame('Tower Inspection', $booking->activityTitle());
        $this->assertSame($audienceId, $booking->audience_id);
    }

    public function test_catalog_activity_that_requires_a_label_is_refused_without_one_on_the_model(): void
    {
        $activity = Activity::factory()->requiresActivityLabel()->create(['name' => 'Climbing Access']);
        $booking = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'activity_label' => 'Kit Checks',
        ]);

        $this->expectException(ValidationException::class);

        $booking->activity_label = null;
        $booking->save();
    }

    public function test_editing_a_private_venue_updates_booking_sequence_and_notifies_attendees(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $booking = Booking::factory()->create();
        $catalogVenueId = (string) $booking->venue_id;
        $originalSequence = $booking->sequence;

        $this->actingAs($manager);
        Event::fake([BookingChanged::class]);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->fillForm([
                'venue_id' => 'other',
                'name' => 'Private meeting room',
                'street' => '2 Example Road',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $booking->refresh();
        $privateVenue = Venue::onlyPrivate()
            ->where('booking_id', $booking->getKey())
            ->firstOrFail();

        $this->assertSame('Private meeting room', $privateVenue->name);
        $this->assertSame($originalSequence + 1, $booking->sequence);
        Event::assertDispatched(BookingChanged::class);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->fillForm(['name' => 'Updated private room'])
            ->call('save')
            ->assertHasNoFormErrors();

        $booking->refresh();
        $privateVenue->refresh();
        $this->assertSame('Updated private room', $privateVenue->name);
        $this->assertSame($originalSequence + 2, $booking->sequence);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->fillForm(['venue_id' => $catalogVenueId])
            ->call('save')
            ->assertHasNoFormErrors();

        $booking->refresh();
        $this->assertSame($catalogVenueId, $booking->venue_id);
        $this->assertSame($originalSequence + 3, $booking->sequence);
        $this->assertDatabaseMissing('venues', ['id' => $privateVenue->getKey()]);
        Event::assertDispatchedTimes(BookingChanged::class, 3);
    }

    public function test_create_booking_defaults_each_field_when_multiple_options_exist(): void
    {
        $user = User::factory()->teamManager()->create();
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
        $user = User::factory()->teamManager()->create();
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
        $user = User::factory()->teamManager()->create();
        Activity::factory()->create(['name' => 'Climbing']);
        Venue::factory()->create(['name' => 'Campsite']);
        $otherTeam = Team::factory()->create();
        $otherTeam->grant($user, MembershipRole::Manager);
        $otherActivity = Activity::factory()->create(['name' => 'Archery']);
        $otherVenue = Venue::factory()->create(['name' => 'Headquarters']);

        $this->actingAs($user);
        Filament::setTenant($otherTeam->refresh());

        Livewire::test(CreateBooking::class)
            ->assertFormSet([
                'activity_id' => $otherActivity->getKey(),
                'venue_id' => $otherVenue->getKey(),
            ]);
    }

    public function test_manager_can_create_a_booking_when_the_team_has_no_catalog_activities(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();

        $this->actingAs($manager);

        Livewire::test(ListBookings::class)
            ->assertActionEnabled('create');

        $this->get(BookingResource::getUrl('create', panel: 'teams', tenant: $team))->assertOk();

        Livewire::test(CreateBooking::class)
            ->assertFormFieldHidden('activity_id')
            ->assertFormFieldVisible('activity_label')
            ->fillForm([
                'activity_label' => 'Planning meeting',
                'venue_id' => 'other',
                'name' => 'Home',
                'start_date' => '2026-10-24',
                'start_time' => '09:30',
                'end_time' => '11:30',
                'timezone' => 'Europe/London',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->firstOrFail();

        $this->assertNull($booking->activity_id);
        $this->assertNull($booking->audience_id);
        $this->assertSame('Planning meeting', $booking->activityTitle());
        $this->assertSame('Home', $booking->venue->name);
        $this->assertTrue(Venue::onlyPrivate()->whereKey($booking->venue_id)->exists());
    }

    public function test_the_booking_form_keeps_activities_catalog_only_and_allows_adding_venues(): void
    {
        $user = User::factory()->teamManager()->create();
        Activity::factory()->create();
        Venue::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->assertFormComponentActionDoesNotExist('activity_id', 'createOption')
            ->assertFormComponentActionExists('venue_id', 'createOption')
            ->mountFormComponentAction('venue_id', 'createOption')
            ->assertMountedActionModalSee(['Venue details', 'Create new venue', 'Use this venue', 'Cancel'])
            ->assertMountedActionModalSeeHtml('isLastStep() ? $wire.callMountedAction() : requestNextStep()');
    }

    public function test_booking_venue_create_option_can_add_a_similar_catalog_venue_to_the_team(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        Activity::factory()->create();
        $otherTeam = Team::factory()->create();
        $venue = Venue::factory()->create(['name' => 'Fox Coverts Hall']);
        $otherTeam->addVenue($venue);

        $this->actingAs($manager);

        Livewire::test(CreateBooking::class)
            ->callFormComponentAction('venue_id', 'createOption', [
                'name' => 'Fox Coverts Hall',
                'similar_venue_id' => $venue->getKey(),
            ])
            ->assertHasNoFormErrors()
            ->assertFormSet(['venue_id' => $venue->getKey()]);

        $this->assertTrue($team->venues()->whereKey($venue->getKey())->exists());
        $this->assertSame(1, Venue::query()->where('name', 'Fox Coverts Hall')->count());
    }

    public function test_booking_venue_create_option_can_create_a_new_catalog_venue(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        Activity::factory()->create();

        $this->actingAs($manager);

        Livewire::test(CreateBooking::class)
            ->callFormComponentAction('venue_id', 'createOption', [
                'name' => 'New Local Hall',
                'street' => '5 Local Road',
                'city' => 'Anytown',
            ])
            ->assertHasNoFormErrors();

        $venue = Venue::query()->where('name', 'New Local Hall')->firstOrFail();

        $this->assertSame('5 Local Road', $venue->street);
        $this->assertTrue($team->venues()->whereKey($venue->getKey())->exists());
    }

    public function test_booking_can_use_a_catalog_venue_from_another_team(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $activity = Activity::factory()->create();
        $audienceId = $this->offerAudience($activity);
        $otherTeam = Team::factory()->create();
        $venue = Venue::factory()->create(['name' => 'Shared Catalog Hall']);
        $otherTeam->addVenue($venue);

        $this->actingAs($manager);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->getKey(),
                'audience_id' => $audienceId,
                'venue_id' => $venue->getKey(),
                'start_date' => '2026-10-24',
                'start_time' => '09:30',
                'end_time' => '11:30',
                'timezone' => 'Europe/London',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->firstOrFail();

        $this->assertSame($venue->getKey(), $booking->venue_id);
        $this->assertTrue($team->venues()->whereKey($venue->getKey())->exists());
    }

    public function test_booking_form_places_a_searchable_timezone_select_with_grouped_utc_offsets(): void
    {
        $user = User::factory()->teamManager()->create();
        Activity::factory()->create();
        Venue::factory()->create();

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

        $user = User::factory()->teamManager()->create([
            'timezone' => 'America/New_York',
        ]);
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $audienceId = $this->offerAudience($activity);

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
                'audience_id' => $audienceId,
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

        $user = User::factory()->teamManager()->create([
            'timezone' => 'America/New_York',
        ]);
        Activity::factory()->create();
        Venue::factory()->create();

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

        $user = User::factory()->teamManager()->create([
            'timezone' => 'America/New_York',
        ]);
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $audienceId = $this->offerAudience($activity);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->id,
                'audience_id' => $audienceId,
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

        $user = User::factory()->teamManager()->create([
            'timezone' => 'America/New_York',
        ]);
        Activity::factory()->create();
        Venue::factory()->create();

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
        $user = User::factory()->teamManager()->create([
            'timezone' => 'America/New_York',
        ]);
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $audienceId = $this->offerAudience($activity);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->id,
                'audience_id' => $audienceId,
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
        $user = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'start_at' => Carbon::parse('2026-12-20 13:00:00', 'UTC'),
            'end_at' => Carbon::parse('2026-12-20 16:00:00', 'UTC'),
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($user);

        Livewire::test(ListBookings::class)
            ->callAction(TestAction::make('edit')->table($booking), [
                'start_time' => '10:00',
            ])
            ->assertHasNoFormErrors();

        $booking->refresh();
        $this->assertSame('2026-12-20 15:00', $booking->start_at->utc()->format('Y-m-d H:i'));
        $this->assertSame('2026-12-20 18:00', $booking->end_at->utc()->format('Y-m-d H:i'));
    }

    public function test_list_create_action_combines_the_schedule_fields_before_saving(): void
    {
        $user = User::factory()->teamManager()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $audienceId = $this->offerAudience($activity);

        $this->actingAs($user);

        Livewire::test(ListBookings::class)
            ->callAction('create', data: [
                'activity_id' => $activity->id,
                'audience_id' => $audienceId,
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
        $user = User::factory()->teamManager()->create([
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
            ->get('/teams/'.$user->teams()->first()->slug.'/bookings/create')
            ->assertForbidden();
    }

    private function offerAudience(Activity $activity): string
    {
        $pairing = ActivityAudience::factory()->for($activity)->create();
        Team::query()->orderByDesc('id')->firstOrFail()->setDefaultAudience($activity, $pairing->audience);

        return (string) $pairing->audience_id;
    }

    private function isEndDateVisible(FusedGroup $group): bool
    {
        $field = $group->getChildSchema()?->getFlatFields(withHidden: true)['end_date'] ?? null;

        return $field instanceof DatePicker && $field->isVisible();
    }
}
