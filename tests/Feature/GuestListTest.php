<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking as ManageViewBooking;
use App\Filament\Manage\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Filament\Team\Resources\Bookings\Pages\ViewBooking as TeamViewBooking;
use App\Filament\Team\Resources\Bookings\RelationManagers\AttendeesRelationManager as TeamAttendeesRelationManager;
use App\Livewire\SessionRoster;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuestListTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_members_see_only_expected_status_groups_and_guest_badges(): void
    {
        $manager = User::factory()->teamLeader()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $accepted = User::factory()->create(['name' => 'Accepted attendee']);
        $tentative = User::factory()->create(['name' => 'Tentative attendee']);
        $declined = User::factory()->create(['name' => 'Declined attendee']);
        $awaiting = User::factory()->create(['name' => 'Awaiting attendee']);
        $suspended = User::factory()->create(['name' => 'Suspended attendee', 'suspended' => true]);
        $team->grant($suspended, MembershipRole::Member);
        $formerMember = User::factory()->guest()->create(['name' => 'Former member']);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $manager->getKey(),
        ]);

        foreach ([
            [$manager, BookingAttendeeStatus::Accepted],
            [$accepted, BookingAttendeeStatus::Accepted],
            [$tentative, BookingAttendeeStatus::Tentative],
            [$declined, BookingAttendeeStatus::Declined],
            [$awaiting, BookingAttendeeStatus::NeedsAction],
            [$suspended, BookingAttendeeStatus::Accepted],
            [$formerMember, BookingAttendeeStatus::Accepted],
        ] as [$attendee, $status]) {
            $booking->attendees()->attach($attendee->getKey(), [
                'status' => $status->value,
                'token' => BookingAttendance::generateToken(),
            ]);
        }

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($member);

        $view = Livewire::test(SessionRoster::class, ['booking' => $booking]);
        $html = $view->html();

        $view
            ->assertSee('Lead Instructor')
            ->assertSee('Accepted attendee')
            ->assertSee('Tentative attendee')
            ->assertSee('Former member')
            ->assertSee('Guest')
            ->assertDontSee('Declined attendee')
            ->assertDontSee('Awaiting attendee')
            ->assertDontSee('Suspended attendee');

        $leadPosition = strpos($html, 'Lead Instructor');
        $acceptedPosition = strpos($html, 'Can help');
        $tentativePosition = strpos($html, 'Maybe');

        $this->assertIsInt($leadPosition);
        $this->assertIsInt($acceptedPosition);
        $this->assertIsInt($tentativePosition);
        $this->assertLessThan($acceptedPosition, $leadPosition);
        $this->assertLessThan($tentativePosition, $acceptedPosition);
        $this->assertStringContainsString(
            'wire:key="guest-group-accepted"',
            $html,
        );
        $this->assertStringContainsString('x-data="{ expanded: true }"', $html);
        $this->assertStringContainsString('x-on:click="expanded = ! expanded"', $html);
        $this->assertStringContainsString('x-bind:aria-expanded="expanded"', $html);
        $this->assertStringContainsString('wire:key="guest-group-tentative"', $html);
    }

    public function test_lead_instructors_see_declined_and_awaiting_attendees(): void
    {
        $lead = User::factory()->teamLeader()->create();
        $team = $lead->managedTeams()->firstOrFail();
        $declined = User::factory()->create(['name' => 'Cannot attend']);
        $awaiting = User::factory()->create(['name' => 'No response']);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $lead->getKey(),
        ]);
        $booking->attendees()->attach($lead->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($declined->getKey(), [
            'status' => BookingAttendeeStatus::Declined->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($awaiting->getKey(), [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($lead);

        Livewire::test(SessionRoster::class, ['booking' => $booking])
            ->assertSee('Cannot attend')
            ->assertSee('No response')
            ->assertSee('Cannot make it')
            ->assertSee('Awaiting response');
    }

    public function test_members_see_the_read_only_relation_manager_guest_list(): void
    {
        $manager = User::factory()->teamLeader()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create(['name' => 'Current attendee']);
        $team->grant($member, MembershipRole::Member);
        $declined = User::factory()->create(['name' => 'Declined attendee']);
        $team->grant($declined, MembershipRole::Member);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $manager->getKey(),
        ]);
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($declined->getKey(), [
            'status' => BookingAttendeeStatus::Declined->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($member);

        Livewire::test(TeamAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamViewBooking::class,
        ])
            ->assertSee('Guest list')
            ->assertSee('Current attendee')
            ->assertDontSee('Declined attendee')
            ->assertDontSee('Edit')
            ->assertDontSee('Remove')
            ->assertDontSee('Add')
            ->assertDontSee('Invite');
    }

    public function test_non_members_cannot_mount_the_member_guest_list_relation_manager(): void
    {
        $manager = User::factory()->teamLeader()->create();
        $booking = Booking::factory()->confirmed()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs(User::factory()->guest()->create());

        Livewire::test(TeamAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamViewBooking::class,
        ])->assertForbidden();
    }

    public function test_managers_see_suspended_attendees_and_all_status_groups_in_members(): void
    {
        $manager = User::factory()->teamLeader()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $suspended = User::factory()->create(['name' => 'Suspended attendee', 'suspended' => true]);
        $team->grant($suspended, MembershipRole::Member);
        $declined = User::factory()->create(['name' => 'Declined attendee']);
        $team->grant($declined, MembershipRole::Member);
        $awaiting = User::factory()->create(['name' => 'Awaiting attendee']);
        $team->grant($awaiting, MembershipRole::Member);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $manager->getKey(),
        ]);
        $booking->attendees()->attach($manager->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($suspended->getKey(), [
            'status' => BookingAttendeeStatus::Tentative->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($declined->getKey(), [
            'status' => BookingAttendeeStatus::Declined->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($awaiting->getKey(), [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        Livewire::test(SessionRoster::class, ['booking' => $booking])
            ->assertSee('Suspended attendee')
            ->assertSee('Declined attendee')
            ->assertSee('Cannot make it')
            ->assertSee('Awaiting attendee')
            ->assertSee('Awaiting response')
            ->assertDontSeeHtml('guest-badge');
    }

    public function test_manage_guest_list_is_grouped_and_retains_attendance_actions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $manager = User::factory()->teamLeader()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create(['name' => 'Team attendee']);
        $team->grant($member, MembershipRole::Member);
        $accepted = User::factory()->create(['name' => 'Accepted attendee']);
        $team->grant($accepted, MembershipRole::Member);
        $declined = User::factory()->create(['name' => 'Declined attendee']);
        $team->grant($declined, MembershipRole::Member);
        $formerMember = User::factory()->guest()->create(['name' => 'Former member']);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $manager->getKey(),
        ]);
        $booking->attendees()->attach($manager->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::Tentative->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($accepted->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($declined->getKey(), [
            'status' => BookingAttendeeStatus::Declined->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($formerMember->getKey(), [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->actingAs($manager);
        $attendee = $booking->attendees()->whereKey($member->getKey())->firstOrFail();
        $this->assertTrue($manager->can('override', $attendee->attendance));
        $this->assertTrue($manager->can('delete', $attendee->attendance));

        $relationManager = Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ManageViewBooking::class,
        ]);

        $relationManager
            ->assertSee('Lead Instructor')
            ->assertSee('Can help')
            ->assertSee('Maybe')
            ->assertSee('Awaiting response')
            ->assertSee('Former member')
            ->assertSee('Guest')
            ->assertSee('Edit')
            ->assertSee('Remove')
            ->assertSee('Add')
            ->assertSee('Invite')
            ->assertDontSeeHtml('<h3 class="invite-heading">Guest list</h3>')
            ->assertSeeHtml("wire:click=\"mountTableAction('editAttendance', '{$member->getKey()}')\"");

        $this->assertSame(0, substr_count($relationManager->html(), '<h3 class="invite-heading">'));
    }
}
