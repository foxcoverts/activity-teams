<?php

namespace Tests\Feature;

use App\Actions\InviteAttendeesAction;
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
        // Viewer name also uses an apostrophe so comments avatar alt correctly emits &#039; —
        // that must not fail the timeline check (page-wide assertDontSeeHtml('D&#039;Amore') was flaky).
        $manager = User::factory()->withApostropheInName("Joana D'Amore II")->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->withApostropheInName("Alyce D'Amore III")->create();
        $team->grant($member, MembershipRole::Member);

        $this->actingAs($manager);
        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        $booking->attendees()->attach($member->id);

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Confirmed booking')
            ->assertSee('Group')
            ->assertSee('Recent activity')
            ->assertSee($manager->name, escape: false)
            ->assertSeeHtml('<strong>'.$manager->name.'</strong>')
            ->assertDontSeeHtml('D&amp;#039;Amore')
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
        $manager = User::factory()->withApostropheInName("Manager O'Brien")->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->withApostropheInName("Member O'Brien")->create();
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
            ->assertSeeHtml("<strong>{$manager->name}</strong>")
            ->assertSee('recorded that you')
            ->assertSeeHtml('<strong>can help</strong>')
            ->assertDontSeeHtml("<strong>{$member->name}</strong>")
            ->assertDontSeeHtml('O&amp;#039;Brien')
            ->assertDontSeeHtml('/members/');
    }

    public function test_the_teams_timeline_links_a_teammate_to_the_member_page(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->withApostropheInName("Manager O'Brien")->teamManager()->create();
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
        $previousStart = $booking->start_at->copy()->timezone(config('app.timezone'))->format('j M Y, H:i');
        $previousEnd = $booking->end_at->copy()->timezone(config('app.timezone'))->format('j M Y, H:i');
        $booking->start_at = $booking->start_at->copy()->addHour();
        $booking->end_at = $booking->end_at->copy()->addHour();
        $booking->save();
        $nextStart = $booking->start_at->copy()->timezone(config('app.timezone'))->format('j M Y, H:i');
        $nextEnd = $booking->end_at->copy()->timezone(config('app.timezone'))->format('j M Y, H:i');

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('made some changes')
            ->assertSeeHtml('<ul>')
            ->assertSeeHtml('<li>changed the <strong>start</strong>')
            ->assertSeeHtml('<li>changed the <strong>end</strong>')
            ->assertSee($previousStart)
            ->assertSee($nextStart)
            ->assertSee($previousEnd)
            ->assertSee($nextEnd)
            ->assertDontSee($booking->start_at->format('Y-m-d H:i:s'));
    }

    public function test_note_changes_name_the_field_without_dumping_the_text(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create([
            'notes' => 'Previous team notes should not appear in the timeline.',
            'lead_instructor_notes' => null,
        ]);
        $booking->notes = 'Current team notes live on the booking itself.';
        $booking->lead_instructor_notes = 'Current lead notes live on the booking itself.';
        $booking->save();

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('made some changes')
            ->assertSeeHtml('updated the <strong>team notes</strong>')
            ->assertSeeHtml('set the <strong>lead instructor notes</strong>')
            ->assertDontSee('Previous team notes should not appear in the timeline.')
            ->assertDontSee('changed the team notes from');
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

    public function test_self_invite_uses_themself_on_the_manager_timeline(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create(['name' => 'Phill Sparks']);
        $team = $manager->managedTeams()->firstOrFail();

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        app(InviteAttendeesAction::class)($booking, [$manager->getKey()], $manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('invited')
            ->assertSee('themself')
            ->assertDontSee('invited Phill Sparks');
    }

    public function test_members_do_not_see_invite_events(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create(['name' => 'Manager']);
        $team = $manager->managedTeams()->firstOrFail();
        $invitee = User::factory()->create(['name' => 'Casey']);
        $onlooker = User::factory()->create(['name' => 'Onlooker']);
        $team->grant($invitee, MembershipRole::Member);
        $team->grant($onlooker, MembershipRole::Member);

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        app(InviteAttendeesAction::class)($booking, [$invitee->getKey()], $manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('invited')
            ->assertSee('Casey');

        $this->actingAs($onlooker);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertDontSee('invited')
            ->assertDontSee('Casey');
    }

    public function test_managers_can_preview_the_member_timeline(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create(['name' => 'Manager']);
        $team = $manager->managedTeams()->firstOrFail();
        $invitee = User::factory()->create(['name' => 'Casey']);
        $team->grant($invitee, MembershipRole::Member);

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        app(InviteAttendeesAction::class)($booking, [$invitee->getKey()], $manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('View as member')
            ->assertSee('invited')
            ->assertSee('Casey')
            ->call('toggleTimelineAudience')
            ->assertSet('timelineAsMember', true)
            ->assertSee('Viewing as member')
            ->assertDontSee('invited')
            ->assertDontSeeHtml('/members/')
            ->call('toggleTimelineAudience')
            ->assertSet('timelineAsMember', false)
            ->assertSee('View as member')
            ->assertSee('invited')
            ->assertSee('Casey');
    }

    public function test_other_members_see_overrides_as_if_the_attendee_responded(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create(['name' => 'Manager']);
        $team = $manager->managedTeams()->firstOrFail();
        $attendee = User::factory()->create(['name' => 'Casey']);
        $onlooker = User::factory()->create(['name' => 'Onlooker']);
        $team->grant($attendee, MembershipRole::Member);
        $team->grant($onlooker, MembershipRole::Member);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create();
        $booking->attendees()->attach($attendee->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        $this->actingAs($manager);
        app(OverrideAttendanceAction::class)($booking, $attendee, $manager, BookingAttendeeStatus::Accepted);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('recorded')
            ->assertSee('Can help')
            ->assertSee('Casey');

        $this->actingAs($onlooker);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSeeHtml('<strong>Casey</strong>')
            ->assertSee('said they')
            ->assertSeeHtml('<strong>can help</strong>')
            ->assertDontSee('recorded');
    }

    public function test_members_do_not_see_lead_instructor_notes_changes_unless_they_are_lead(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $lead = User::factory()->create(['name' => 'Lead']);
        $member = User::factory()->create(['name' => 'Member']);
        $team->grant($lead, MembershipRole::Member);
        $team->grant($member, MembershipRole::Member);

        $this->actingAs($manager);

        $booking = Booking::factory()->confirmed()->forTeam($team)->create([
            'lead_instructor_id' => $lead->getKey(),
            'requires_lead_instructor' => true,
            'lead_instructor_notes' => null,
            'notes' => null,
        ]);
        $booking->attendees()->attach($lead->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);
        $booking->lead_instructor_notes = 'Bring helmets';
        $booking->save();

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSeeHtml('set the <strong>lead instructor notes</strong>');

        $this->actingAs($member);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertDontSee('lead instructor notes');

        $this->actingAs($lead);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSeeHtml('set the <strong>lead instructor notes</strong>');
    }
}
