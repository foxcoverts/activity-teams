<?php

namespace Tests\Feature;

use App\Actions\OverrideAttendanceAction;
use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking as ManageViewBooking;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Filament\Member\Resources\Bookings\Pages\ViewBooking as MemberViewBooking;
use App\Livewire\SessionRoster;
use App\Models\Booking;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_member_timeline_does_not_link_into_manage(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();

        $this->actingAs($manager);
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($member->id);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($member);

        $view = Livewire::test(MemberViewBooking::class, ['record' => $booking->getRouteKey()]);

        $view
            ->assertSee('Confirmed booking')
            ->assertSee('Group')
            ->assertSee('Recent activity')
            ->assertSee($manager->name)
            ->assertSeeHtml('wire:name="App\\Filament\\Member\\Resources\\Bookings\\RelationManagers\\AttendeesRelationManager"')
            ->assertDontSeeHtml('/manage/');
    }

    public function test_only_current_team_members_can_see_the_guest_list(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);

        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('member'));

        $this->actingAs($member);

        $view = Livewire::test(MemberViewBooking::class, ['record' => $booking->getRouteKey()]);

        $view
            ->assertSee('Confirmed booking')
            ->assertSeeHtml('wire:name="App\\Filament\\Member\\Resources\\Bookings\\RelationManagers\\AttendeesRelationManager"')
            ->assertSeeHtml('--col-span-lg: span 3 / span 3;')
            ->assertSeeHtml('--col-span-lg: span 2 / span 2;');

        $outsider = User::factory()->guest()->create();
        $this->actingAs($outsider);

        Livewire::test(MemberViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertNotFound();

        Livewire::test(SessionRoster::class, ['booking' => $booking])
            ->assertForbidden();
    }

    public function test_the_member_timeline_emboldens_people_who_are_not_links(): void
    {
        $manager = User::factory()->teamManager()->create(['name' => 'Manager A']);
        $member = User::factory()->teamMember()->create(['name' => 'Member B']);
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        $this->actingAs($manager);

        app(OverrideAttendanceAction::class)($booking, $member, $manager, BookingAttendeeStatus::Accepted);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($member);

        Livewire::test(MemberViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSeeHtml('<strong>Manager A</strong>')
            ->assertSeeHtml('<strong>Can help</strong>')
            ->assertSeeHtml('<strong>Member B</strong>')
            ->assertDontSeeHtml('/manage/');
    }

    public function test_the_manage_timeline_links_a_teammate_to_the_member_page(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);
        $booking = Booking::factory()->confirmed()->create();

        $memberUrl = MemberResource::getUrl('view', ['record' => $manager], panel: 'manage', tenant: $team);

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Confirmed booking')
            ->assertSee('Group')
            ->assertSee('Recent activity')
            ->assertSee($manager->name)
            ->assertSeeHtml($memberUrl)
            ->assertDontSeeHtml('/users/'.$manager->getKey());
    }

    public function test_the_manage_timeline_links_the_person_a_change_is_about(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create(['name' => 'Casey']);
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->create();
        $booking->lead_instructor_id = $member->getKey();
        $booking->save();

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Casey')
            ->assertSeeHtml(MemberResource::getUrl('view', ['record' => $member], panel: 'manage', tenant: $team))
            ->assertDontSeeHtml('/users/'.$member->getKey());
    }

    public function test_recording_your_own_attendance_does_not_name_you_twice(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create(['name' => 'Casey']);
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        (new RespondToBookingAction($booking, $member))($member, BookingAttendeeStatus::Tentative);

        $this->actingAs($manager);

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('said')
            ->assertSee('maybe')
            ->assertDontSee('said they')
            ->assertDontSee('recorded Maybe for');
    }

    public function test_several_field_changes_are_listed_under_one_intro(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $manager = User::factory()->teamManager()->create();

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->create();
        $booking->start_at = $booking->start_at->copy()->addHour();
        $booking->end_at = $booking->end_at->copy()->addHour();
        $booking->save();

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('made some changes')
            ->assertSeeHtml('<ul>')
            ->assertSeeHtml('<li><strong>start at</strong>')
            ->assertSeeHtml('<li><strong>end at</strong>');
    }
}
