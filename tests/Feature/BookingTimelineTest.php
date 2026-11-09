<?php

namespace Tests\Feature;

use App\Actions\OverrideAttendanceAction;
use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking as TeamsViewBooking;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\ActivityAudience;
use App\Models\Audience;
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
        // Apostrophe regression: timeline must show a raw ', never double-encoded &amp;#039;.
        $manager = User::factory()->teamManager()->create(['name' => "Joana D'Amore II"]);
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);

        $this->actingAs($manager);
        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        $booking->attendees()->attach($member->id);

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        $view = Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()]);

        $view
            ->assertSee('Confirmed booking')
            ->assertSee('Group')
            ->assertSee('Recent activity')
            ->assertSee($manager->name, escape: false)
            ->assertSeeHtml("<strong>Joana D'Amore II</strong>")
            ->assertDontSeeHtml('D&amp;#039;Amore')
            ->assertDontSeeHtml('D&#039;Amore')
            ->assertSeeHtml('wire:name="App\\Filament\\Teams\\Resources\\Bookings\\RelationManagers\\AttendeesRelationManager"')
            ->assertDontSeeHtml('/members/');
    }

    public function test_only_current_team_members_can_see_the_guest_list(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create([
            'team_id' => $team->getKey(),
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        $view = Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()]);

        $view
            ->assertSee('Confirmed booking')
            ->assertSeeHtml('wire:name="App\\Filament\\Teams\\Resources\\Bookings\\RelationManagers\\AttendeesRelationManager"')
            ->assertSeeHtml('--col-span-lg: span 3 / span 3;')
            ->assertSeeHtml('--col-span-lg: span 2 / span 2;');

        $outsider = User::factory()->guest()->create();
        $this->actingAs($outsider);
        Filament::setTenant($booking->team);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertNotFound();
    }

    public function test_the_member_timeline_emboldens_people_who_are_not_links(): void
    {
        $manager = User::factory()->teamManager()->create(['name' => "Manager O'Brien"]);
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create(['name' => "Member O'Brien"]);
        $team->grant($member, MembershipRole::Member);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        $this->actingAs($manager);

        app(OverrideAttendanceAction::class)($booking, $member, $manager, BookingAttendeeStatus::Accepted);

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSeeHtml("<strong>Manager O'Brien</strong>")
            ->assertSeeHtml('<strong>Can help</strong>')
            ->assertSeeHtml("<strong>Member O'Brien</strong>")
            ->assertDontSeeHtml('O&amp;#039;Brien')
            ->assertDontSeeHtml('/members/');
    }

    public function test_the_teams_timeline_links_a_teammate_to_the_member_page(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create(['name' => "Manager O'Brien"]);
        $team = $manager->managedTeams()->firstOrFail();

        $this->actingAs($manager);
        $booking = Booking::factory()->confirmed()->forTeam($team)->create();

        $memberUrl = MemberResource::getUrl('view', ['record' => $manager], panel: 'teams', tenant: $team);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Confirmed booking')
            ->assertSee('Group')
            ->assertSee('Recent activity')
            ->assertSee($manager->name, escape: false)
            ->assertSeeHtml($memberUrl)
            ->assertDontSeeHtml('/users/'.$manager->getKey());
    }

    public function test_the_teams_timeline_links_the_person_a_change_is_about(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create(['name' => 'Casey']);
        $team->grant($member, MembershipRole::Member);

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        $booking->lead_instructor_id = $member->getKey();
        $booking->save();

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Casey')
            ->assertSeeHtml(MemberResource::getUrl('view', ['record' => $member], panel: 'teams', tenant: $team))
            ->assertDontSeeHtml('/users/'.$member->getKey());
    }

    public function test_recording_your_own_attendance_does_not_name_you_twice(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create(['name' => 'Casey']);
        $team->grant($member, MembershipRole::Member);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        (new RespondToBookingAction($booking, $member))($member, BookingAttendeeStatus::Tentative);

        $this->actingAs($manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('said')
            ->assertSee('maybe')
            ->assertDontSee('said they')
            ->assertDontSee('recorded Maybe for');
    }

    public function test_stepping_down_as_lead_is_one_timeline_entry(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $lead = User::factory()->create(['name' => 'Casey']);
        $team->grant($lead, MembershipRole::Member);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create([
            'lead_instructor_id' => $lead->getKey(),
        ]);
        $booking->attendees()->attach($lead->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        (new RespondToBookingAction($booking, $lead))($lead, BookingAttendeeStatus::Declined);

        $this->actingAs($manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Casey')
            ->assertSee('said they')
            ->assertSee('cannot make it')
            ->assertSee('stepped down as lead instructor')
            ->assertSee('leaving the booking tentative')
            ->assertDontSee('made some changes');
    }

    public function test_several_field_changes_are_listed_under_one_intro(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        $booking->start_at = $booking->start_at->copy()->addHour();
        $booking->end_at = $booking->end_at->copy()->addHour();
        $booking->save();

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('made some changes')
            ->assertSeeHtml('<ul>')
            ->assertSeeHtml('<li>changed the <strong>start at</strong>')
            ->assertSeeHtml('<li>changed the <strong>end at</strong>');
    }

    public function test_audience_changes_show_the_audience_name_not_the_id(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        $from = Audience::query()->findOrFail($booking->audience_id);
        $from->forceFill(['name' => 'Beavers'])->save();
        $to = Audience::factory()->create(['name' => 'Scouts']);
        ActivityAudience::factory()->create([
            'activity_id' => $booking->activity_id,
            'audience_id' => $to->getKey(),
        ]);

        $booking->audience_id = $to->getKey();
        $booking->save();

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSeeHtml('<strong>audience</strong>')
            ->assertSee('Beavers')
            ->assertSee('Scouts')
            ->assertDontSee($from->getKey())
            ->assertDontSee($to->getKey());
    }

    public function test_setting_a_blank_field_says_set_to_not_empty_field(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create(['name' => 'Joe Climber']);
        $team->grant($member, MembershipRole::Member);

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create([
            'lead_instructor_id' => null,
        ]);
        $booking->lead_instructor_id = $member->getKey();
        $booking->save();

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('set the')
            ->assertSee('lead instructor')
            ->assertSee('Joe Climber')
            ->assertDontSee('updated the lead instructor')
            ->assertDontSee('empty field');
    }

    public function test_clearing_a_field_says_removed_not_empty_field(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create(['name' => 'Joe Climber']);
        $team->grant($member, MembershipRole::Member);

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create([
            'lead_instructor_id' => $member->getKey(),
        ]);
        $booking->lead_instructor_id = null;
        $booking->save();

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('removed')
            ->assertSee('Joe Climber')
            ->assertSee('as the')
            ->assertSee('lead instructor')
            ->assertDontSee('empty field');
    }

    public function test_changes_without_a_causer_still_read_as_a_sentence(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create([
            'lead_instructor_id' => null,
        ]);

        auth()->logout();

        $booking->start_at = $booking->start_at->copy()->addHour();
        $booking->end_at = $booking->end_at->copy()->addHour();
        $booking->save();

        $this->assertNull($booking->activities()->where('event', 'updated')->first()?->causer_id);

        $this->actingAs($manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Made some changes')
            ->assertDontSee('made some changes');
    }

    public function test_a_single_change_without_a_causer_starts_with_a_capital_letter(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create(['name' => 'Joe Climber']);
        $team->grant($member, MembershipRole::Member);

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create([
            'lead_instructor_id' => null,
        ]);

        auth()->logout();

        $booking->lead_instructor_id = $member->getKey();
        $booking->save();

        $this->actingAs($manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Set the')
            ->assertSee('Joe Climber')
            ->assertDontSee('set the lead instructor');
    }
}
