<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking as ManageViewBooking;
use App\Filament\Manage\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking as MemberViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager as MemberAttendeesRelationManager;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class GuestListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array{0: User, 1: BookingAttendeeStatus}>  $attendees
     */
    private function attendAll(Booking $booking, array $attendees): void
    {
        foreach ($attendees as [$attendee, $status]) {
            $booking->attendees()->attach($attendee->getKey(), [
                'status' => $status->value,
                'token' => BookingAttendance::generateToken(),
            ]);
        }
    }

    private function memberGuestList(Booking $booking): Testable
    {
        Filament::setCurrentPanel('member');

        return Livewire::test(MemberAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => MemberViewBooking::class,
        ]);
    }

    public function test_regular_members_see_only_expected_status_groups_and_guest_badges(): void
    {
        $manager = User::factory()->teamManager()->create(['name' => 'Lead Person']);
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $accepted = User::factory()->create(['name' => 'Accepted attendee']);
        $tentative = User::factory()->create(['name' => 'Tentative attendee']);
        $declined = User::factory()->create(['name' => 'Declined attendee']);
        $awaiting = User::factory()->create(['name' => 'Awaiting attendee']);
        $suspended = User::factory()->suspended()->create(['name' => 'Suspended attendee']);
        $team->grant($suspended, MembershipRole::Member);
        $formerMember = User::factory()->guest()->create(['name' => 'Former member']);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $manager->getKey(),
        ]);
        $this->attendAll($booking, [
            [$manager, BookingAttendeeStatus::Accepted],
            [$accepted, BookingAttendeeStatus::Accepted],
            [$tentative, BookingAttendeeStatus::Tentative],
            [$declined, BookingAttendeeStatus::Declined],
            [$awaiting, BookingAttendeeStatus::NeedsAction],
            [$suspended, BookingAttendeeStatus::Accepted],
            [$formerMember, BookingAttendeeStatus::Accepted],
        ]);

        $this->actingAs($member);
        $view = $this->memberGuestList($booking);
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
        $this->assertStringContainsString('wire:key="guest-group-accepted"', $html);
        $this->assertStringContainsString('wire:key="guest-group-tentative"', $html);
        $this->assertStringContainsString('x-data="{ expanded: true }"', $html);
        $this->assertStringContainsString('x-on:click="expanded = ! expanded"', $html);
    }

    public function test_lead_instructors_see_declined_and_awaiting_attendees(): void
    {
        $lead = User::factory()->teamMember()->create();
        $team = $lead->teams()->firstOrFail();
        $declined = User::factory()->create(['name' => 'Declined attendee']);
        $awaiting = User::factory()->create(['name' => 'Awaiting attendee']);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $lead->getKey(),
        ]);
        $this->attendAll($booking, [
            [$lead, BookingAttendeeStatus::Accepted],
            [$declined, BookingAttendeeStatus::Declined],
            [$awaiting, BookingAttendeeStatus::NeedsAction],
        ]);

        $this->actingAs($lead);

        $this->memberGuestList($booking)
            ->assertSee('Declined attendee')
            ->assertSee('Cannot make it')
            ->assertSee('Awaiting attendee')
            ->assertSee('Awaiting response');
    }

    public function test_managers_see_suspended_attendees_and_all_status_groups_without_guest_badges(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $suspended = User::factory()->suspended()->create(['name' => 'Suspended attendee']);
        $team->grant($suspended, MembershipRole::Member);
        $declined = User::factory()->create(['name' => 'Declined attendee']);
        $team->grant($declined, MembershipRole::Member);
        $awaiting = User::factory()->create(['name' => 'Awaiting attendee']);
        $team->grant($awaiting, MembershipRole::Member);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $manager->getKey(),
        ]);
        $this->attendAll($booking, [
            [$manager, BookingAttendeeStatus::Accepted],
            [$suspended, BookingAttendeeStatus::Tentative],
            [$declined, BookingAttendeeStatus::Declined],
            [$awaiting, BookingAttendeeStatus::NeedsAction],
        ]);

        $this->actingAs($manager);

        $this->memberGuestList($booking)
            ->assertSee('Suspended attendee')
            ->assertSee('Declined attendee')
            ->assertSee('Cannot make it')
            ->assertSee('Awaiting attendee')
            ->assertSee('Awaiting response')
            ->assertDontSeeHtml('guest-badge');
    }

    public function test_members_see_the_read_only_relation_manager_guest_list(): void
    {
        $manager = User::factory()->teamManager()->create();
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

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(MemberAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => MemberViewBooking::class,
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
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->confirmed()->create();

        Filament::setCurrentPanel('member');
        $this->actingAs(User::factory()->guest()->create());

        Livewire::test(MemberAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => MemberViewBooking::class,
        ])->assertForbidden();
    }

    public function test_manage_guest_list_is_grouped_and_retains_attendance_actions(): void
    {
        Filament::setCurrentPanel('manage');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create(['name' => 'Team attendee']);
        $team->grant($member, MembershipRole::Member);
        $accepted = User::factory()->create(['name' => 'Accepted attendee']);
        $team->grant($accepted, MembershipRole::Member);
        $parent = User::factory()->underEighteen()->create(['name' => 'Parent attendee']);
        $team->grant($parent, MembershipRole::Parent);
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
        $booking->attendees()->attach($parent->getKey(), [
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
            ->assertSee('Parent attendee')
            ->assertSee('Under 18')
            ->assertSee('Parent')
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
