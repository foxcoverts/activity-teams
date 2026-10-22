<?php

namespace Tests\Feature;

use App\Actions\OverrideAttendanceAction;
use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking as ManageViewBooking;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Filament\Team\Resources\Bookings\Pages\ViewBooking as TeamViewBooking;
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
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create();

        $this->actingAs($leader);
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($member->id);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($member);

        $view = Livewire::test(TeamViewBooking::class, ['record' => $booking->getRouteKey()]);

        $view
            ->assertSee('Confirmed booking')
            ->assertSee('Group')
            ->assertSee('Guest list')
            ->assertSee($member->name)
            ->assertSee('Recent activity')
            ->assertSee($leader->name)
            ->assertDontSeeHtml('/manage/');

        $this->assertSame(1, substr_count($view->html(), 'Guest list'));
    }

    public function test_only_current_team_members_can_see_the_guest_list(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $team = $leader->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);

        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('member'));

        $this->actingAs($member);

        $view = Livewire::test(TeamViewBooking::class, ['record' => $booking->getRouteKey()]);

        $view
            ->assertSee('Confirmed booking')
            ->assertSee('Guest list')
            ->assertSeeHtml('--col-span-lg: span 3 / span 3;')
            ->assertSeeHtml('--col-span-lg: span 2 / span 2;');

        $outsider = User::factory()->guest()->create();
        $this->actingAs($outsider);

        Livewire::test(TeamViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertNotFound();

        Livewire::test(SessionRoster::class, ['booking' => $booking])
            ->assertForbidden();
    }

    public function test_the_member_timeline_emboldens_people_who_are_not_links(): void
    {
        $leader = User::factory()->teamLeader()->create(['name' => 'Manager A']);
        $member = User::factory()->teamMember()->create(['name' => 'Member B']);
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        $this->actingAs($leader);

        app(OverrideAttendanceAction::class)($booking, $member, $leader, BookingAttendeeStatus::Accepted);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($member);

        Livewire::test(TeamViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSeeHtml('<strong>Manager A</strong>')
            ->assertSeeHtml('<strong>Can help</strong>')
            ->assertSeeHtml('<strong>Member B</strong>')
            ->assertDontSeeHtml('/manage/');
    }

    public function test_the_manage_timeline_links_a_teammate_to_the_member_page(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $leader = User::factory()->teamLeader()->create();
        $team = $leader->managedTeams()->first();

        $this->actingAs($leader);
        $booking = Booking::factory()->confirmed()->create();

        $memberUrl = MemberResource::getUrl('view', ['record' => $leader], panel: 'manage', tenant: $team);

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Confirmed booking')
            ->assertSee('Group')
            ->assertSee('Recent activity')
            ->assertSee($leader->name)
            ->assertSeeHtml($memberUrl)
            ->assertDontSeeHtml('/users/'.$leader->getKey());
    }

    public function test_the_manage_timeline_links_the_person_a_change_is_about(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create(['name' => 'Casey']);
        $team = $leader->managedTeams()->first();

        $this->actingAs($leader);

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

        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create(['name' => 'Casey']);
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        (new RespondToBookingAction($booking, $member))($member, BookingAttendeeStatus::Tentative);

        $this->actingAs($leader);

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('said')
            ->assertSee('maybe')
            ->assertDontSee('said they')
            ->assertDontSee('recorded Maybe for');
    }

    public function test_several_field_changes_are_listed_under_one_intro(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader);

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
