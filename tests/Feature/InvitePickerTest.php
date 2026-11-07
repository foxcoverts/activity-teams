<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Enums\MembershipRole;
use App\Filament\Teams\Resources\Bookings\Pages\EditBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Livewire\InviteAttendees;
use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Key;
use App\Models\Lock;
use App\Models\Qualification;
use App\Models\User;
use App\Support\MailPreference;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvitePickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_qualified_people_start_queued_and_everyone_else_stays_in_the_picker(): void
    {
        [$manager, $booking, $qualified, $other] = $this->teamWithRequirement();
        User::factory()->create(['name' => 'Outsider Person']);
        $former = User::factory()->teamMember()->create(['name' => 'Pat Former']);
        $booking->team->recordLeft($former);
        $booking->attendees()->attach($former->id, [
            'status' => BookingAttendeeStatus::Declined->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $accepted = User::factory()->teamMember()->create(['name' => 'Casey Helper']);
        $booking->attendees()->attach($accepted->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->actingAs($manager);

        Livewire::test(InviteAttendees::class, ['booking' => $booking])
            ->assertSet('queued', [$qualified->getKey()])
            ->assertSee('To be invited')
            ->assertSee('Sam Holder')
            ->assertSee('Qualified')
            ->assertSee('Alex Guest')
            ->assertSee('Casey Helper')
            ->assertSee('Can help')
            ->assertSee('Pat Former')
            ->assertSee('Cannot make it')
            ->assertDontSee('Outsider Person')
            ->call('add', $accepted->getKey())
            ->assertSet('queued', [$qualified->getKey()])
            ->call('add', $other->getKey())
            ->assertSet('queued', [$qualified->getKey(), $other->getKey()])
            ->call('remove', $other->getKey())
            ->call('remove', $qualified->getKey())
            ->assertSet('queued', [])
            ->call('toggleQualified')
            ->assertDontSee('Alex Guest')
            ->assertSee('Sam Holder')
            ->call('addShown')
            ->assertSet('queued', [$qualified->getKey()]);
    }

    public function test_an_activity_with_no_requirements_starts_with_an_empty_list(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create(['name' => 'Alex Guest']);
        $parent = User::factory()->create(['name' => 'Pat Parent']);
        $manager->managedTeams()->firstOrFail()->grant($parent, MembershipRole::Parent);
        $booking = Booking::factory()->create();

        $this->actingAs($manager);

        Livewire::test(InviteAttendees::class, ['booking' => $booking])
            ->assertSet('queued', [])
            ->assertSee('No one is queued.')
            ->assertSee('Alex Guest')
            ->assertDontSee('No email')
            ->assertDontSee('Qualified only')
            ->assertDontSee('Key holders only')
            ->assertSee('Members only')
            ->assertSee('Pat Parent')
            ->call('toggleMembersOnly')
            ->assertDontSee('Pat Parent')
            ->assertSee('Alex Guest')
            ->assertSet('queued', [])
            ->call('send');

        $this->assertDatabaseMissing('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_qualified_parents_are_not_auto_queued_or_badged_as_parents(): void
    {
        [$manager, $booking, $qualified] = $this->teamWithRequirement();
        $parent = User::factory()->underEighteen()->create(['name' => 'Taylor']);
        $booking->team->grant($parent, MembershipRole::Parent);
        Qualification::factory()->forMember($parent)->mountainTraining()->create();

        $this->actingAs($manager);

        Livewire::test(InviteAttendees::class, ['booking' => $booking])
            ->assertSet('queued', [$qualified->getKey()])
            ->assertSee('Taylor')
            ->assertSee('Under 18')
            ->assertSee('Qualified')
            ->assertDontSee('Parent')
            ->call('toggleMembersOnly')
            ->assertDontSee('Taylor')
            ->assertSet('queued', [$qualified->getKey()]);
    }

    public function test_a_name_filter_narrows_the_picker_and_add_everyone_shown_skips_people_already_on_the_booking(): void
    {
        $manager = User::factory()->teamManager()->create(['name' => 'Alex Leader']);
        $member = User::factory()->teamMember()->create(['name' => 'Casey Member']);
        $activity = Activity::factory()->create();
        ActivityAudience::factory()->for($activity)->create()->qualificationRequirements()->create([
            'kind' => 'mountain_training',
            'criteria' => ['award' => 'Climbing Wall Instructor'],
        ]);
        $booking = Booking::factory()->create(['activity_id' => $activity->getKey()]);
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->actingAs($manager);

        Livewire::test(InviteAttendees::class, ['booking' => $booking])
            ->assertSet('queued', [])
            ->assertSee('Awaiting response')
            ->assertDontSee('Managers')
            ->set('name', 'Casey')
            ->assertSee('Casey Member')
            ->assertDontSee('Alex Leader')
            ->call('addShown')
            ->assertSet('queued', [])
            ->set('name', 'alex')
            ->assertSee('Alex Leader')
            ->assertDontSee('Casey Member')
            ->call('addShown')
            ->assertSet('queued', [$manager->getKey()]);
    }

    public function test_sending_invites_closes_the_modal_and_the_next_open_starts_again(): void
    {
        [$manager, $booking, $qualified, $other] = $this->teamWithRequirement();
        Qualification::factory()->forMember($other)->mountainTraining()->create();

        $this->actingAs($manager);

        Livewire::test(InviteAttendees::class, ['booking' => $booking])
            ->call('remove', $other->getKey())
            ->call('send');

        $this->assertSame(
            BookingAttendeeStatus::NeedsAction,
            $booking->attendees()->find($qualified)?->attendance->status,
        );
        $this->assertNull($booking->attendees()->find($other->getKey()));

        Livewire::test(InviteAttendees::class, ['booking' => $booking->fresh()])
            ->assertSet('queued', [$other->getKey()])
            ->assertSee('Awaiting response');

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => EditBooking::class,
        ])
            ->mountTableAction('invite')
            ->assertTableActionMounted('invite')
            ->dispatch('invites-sent')
            ->assertTableActionNotMounted('invite')
            ->mountTableAction('invite')
            ->dispatch('close-invite')
            ->assertTableActionNotMounted('invite');
    }

    public function test_key_holders_only_narrows_the_team_list_and_leaves_the_queue(): void
    {
        [$manager, $booking, $qualified, $other] = $this->teamWithRequirement();
        $holder = User::factory()->teamMember()->create(['name' => 'Robin Outsider']);
        $key = Lock::factory()->create(['name' => 'Climbing store']);
        $key->requirements()->create([
            'activity_id' => $booking->activity_id,
            'venue_id' => $booking->venue_id,
        ]);
        $ring = Key::factory()->heldBy($holder)->create(['name' => 'Shared climbing']);
        $ring->locks()->attach($key);
        $spare = Lock::factory()->create(['name' => 'Front door']);
        Key::factory()->heldBy($other)->create(['name' => 'Spare door'])->locks()->attach($spare);

        $this->actingAs($manager);

        Livewire::test(InviteAttendees::class, ['booking' => $booking])
            ->assertSet('queued', [$qualified->getKey()])
            ->assertSee('Key holders only')
            ->assertSee('Robin Outsider')
            ->assertSee('Alex Guest')
            ->assertSee('Key holder')
            ->assertSee('Other keys')
            ->call('toggleKeyHolders')
            ->assertSee('Robin Outsider')
            ->assertDontSee('Alex Guest')
            ->assertSet('queued', [$qualified->getKey()])
            ->call('addShown')
            ->assertSet('queued', [$qualified->getKey(), $holder->getKey()])
            ->call('openKeys', $holder->getKey())
            ->assertSee('Shared climbing')
            ->assertSee('Climbing store')
            ->assertDontSee('To be invited')
            ->assertDontSee('Send invites')
            ->call('closeAccess')
            ->assertSee('To be invited')
            ->assertSee('Send invites')
            ->call('openQualifications', $qualified->getKey())
            ->assertSee('Climbing Wall Instructor')
            ->assertDontSee('Show other qualifications');
    }

    public function test_people_with_booking_mail_off_show_a_no_email_badge(): void
    {
        $manager = User::factory()->teamManager()->create();
        $optedOut = User::factory()->teamMember()->create(['name' => 'Riley Quiet']);
        $booking = Booking::factory()->create();

        MailPreference::save($optedOut, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ]);

        $this->actingAs($manager);

        Livewire::test(InviteAttendees::class, ['booking' => $booking])
            ->assertSee('Riley Quiet')
            ->assertSee('No email')
            ->assertSee('Booking emails are turned off');
    }

    /**
     * @return array{User, Booking, User, User}
     */
    private function teamWithRequirement(): array
    {
        $manager = User::factory()->teamManager()->create();
        $qualified = User::factory()->teamMember()->create(['name' => 'Sam Holder']);
        $other = User::factory()->teamMember()->create(['name' => 'Alex Guest']);
        $activity = Activity::factory()->create();
        ActivityAudience::factory()->for($activity)->create()->qualificationRequirements()->create([
            'kind' => 'mountain_training',
            'criteria' => ['award' => 'Climbing Wall Instructor'],
        ]);
        $booking = Booking::factory()->create(['activity_id' => $activity->getKey()]);
        Qualification::factory()->forMember($qualified)->mountainTraining()->create();

        return [$manager, $booking, $qualified, $other];
    }
}
