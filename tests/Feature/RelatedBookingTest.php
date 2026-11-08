<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Bookings\Pages\EditBooking as TeamsEditBooking;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking as TeamsViewBooking;
use App\Livewire\RelatedBookings;
use App\Mail\BookingChangedMail;
use App\Mail\BookingInviteMail;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Team;
use App\Models\User;
use App\Support\BookingMailThread;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RelatedBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_links_another_booking_of_the_same_team_on_both_sides(): void
    {
        [$manager, $saturday, $sunday] = $this->weekend();

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(RelatedBookings::class, [
            'record' => $saturday,
        ])
            ->callAction('add', data: [
                'related_id' => $sunday->getKey(),
            ])
            ->assertSee($sunday->relatedLabel());

        $this->assertTrue($saturday->fresh()->related->contains($sunday));
        $this->assertTrue($sunday->fresh()->related->contains($saturday));
    }

    public function test_removing_a_related_booking_unlinks_both_sides(): void
    {
        [$manager, $saturday, $sunday] = $this->weekend();
        $saturday->relate($sunday);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(RelatedBookings::class, [
            'record' => $saturday,
        ])
            ->call('remove', $sunday->getKey())
            ->assertSee('No related bookings.');

        $this->assertTrue($saturday->fresh()->related->isEmpty());
        $this->assertTrue($sunday->fresh()->related->isEmpty());
    }

    public function test_the_picker_excludes_self_cancelled_past_and_already_linked_bookings(): void
    {
        [$manager, $saturday, $sunday] = $this->weekend();
        $team = $manager->managedTeams()->firstOrFail();
        $cancelled = Booking::factory()->cancelled()->create([
            'team_id' => $team->getKey(),
            'start_at' => '2026-12-14 09:00:00',
            'end_at' => '2026-12-14 11:00:00',
            'timezone' => 'Europe/London',
        ]);
        $past = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'start_at' => '2026-10-01 09:00:00',
            'end_at' => '2026-10-01 11:00:00',
            'timezone' => 'Europe/London',
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(RelatedBookings::class, [
            'record' => $saturday,
        ])
            ->mountAction('add')
            ->assertSchemaComponentExists('related_id', checkComponentUsing: function (Select $select) use ($saturday, $sunday, $cancelled, $past): bool {
                $options = $select->getOptions();

                return isset($options[$sunday->getKey()])
                    && ! isset($options[$saturday->getKey()])
                    && ! isset($options[$cancelled->getKey()])
                    && ! isset($options[$past->getKey()]);
            });
    }

    public function test_a_manager_cannot_link_or_see_another_team_booking_as_related(): void
    {
        [$manager, $saturday] = $this->weekend();
        $otherTeam = Team::factory()->create();
        $otherManager = User::factory()->teamManager($otherTeam)->create();
        $foreign = Booking::factory()->confirmed()->create([
            'team_id' => $otherTeam->getKey(),
            'start_at' => '2026-12-13 09:00:00',
            'end_at' => '2026-12-13 11:00:00',
            'timezone' => 'Europe/London',
            'group_name' => 'Archery camp',
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        $this->assertFalse($manager->can('relate', [$saturday, $foreign]));

        Livewire::test(RelatedBookings::class, [
            'record' => $saturday,
        ])
            ->mountAction('add')
            ->assertSchemaComponentExists('related_id', checkComponentUsing: function (Select $select) use ($foreign): bool {
                return ! isset($select->getOptions()[$foreign->getKey()]);
            });

        Livewire::test(RelatedBookings::class, [
            'record' => $saturday,
        ])
            ->call('add', $foreign->getKey())
            ->assertNotFound();

        $this->assertTrue($saturday->fresh()->related->isEmpty());
    }

    public function test_members_can_view_related_bookings_but_cannot_add_or_remove_them(): void
    {
        [$manager, $saturday, $sunday] = $this->weekend();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $saturday->relate($sunday);

        $this->assertFalse($member->can('relate', [$saturday, $sunday]));

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(RelatedBookings::class, [
            'record' => $saturday,
        ])
            ->assertSee('Related bookings')
            ->assertSee($sunday->relatedLabel())
            ->assertActionHidden('add')
            ->assertDontSee('Remove');

        Livewire::test(RelatedBookings::class, [
            'record' => $saturday,
        ])
            ->call('add', $sunday->getKey())
            ->assertForbidden();
    }

    public function test_members_only_see_related_bookings_they_can_already_view(): void
    {
        [$manager, $saturday] = $this->weekend();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $hidden = Booking::factory()->create([
            'team_id' => $team->getKey(),
            'activity_id' => $saturday->activity_id,
            'start_at' => '2026-12-13 09:00:00',
            'end_at' => '2026-12-13 11:00:00',
            'timezone' => 'Europe/London',
            'group_name' => 'Leaders only',
        ]);
        $saturday->relate($hidden);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(RelatedBookings::class, [
            'record' => $saturday,
        ])
            ->assertDontSee('Related bookings')
            ->assertDontSee('Leaders only')
            ->assertDontSee('No related bookings.');

        Filament::setCurrentPanel('teams');
        Filament::setTenant($saturday->team);
        Livewire::test(TeamsViewBooking::class, ['record' => $saturday->getRouteKey()])
            ->assertDontSee('Related bookings')
            ->assertDontSee('Leaders only');
    }

    public function test_teams_view_pages_stack_the_related_row_under_the_guest_list(): void
    {
        [$manager, $saturday, $sunday] = $this->weekend();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $saturday->relate($sunday);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        $teamsUrl = BookingResource::getUrl(
            'view',
            ['record' => $sunday],
            panel: 'teams',
            tenant: $team,
        );

        Livewire::test(TeamsViewBooking::class, ['record' => $saturday->getRouteKey()])
            ->assertSee('Related bookings')
            ->assertSee($sunday->relatedLabel())
            ->assertSeeHtml($teamsUrl)
            ->assertSeeHtml('class="invite-panel"');

        Livewire::test(TeamsEditBooking::class, ['record' => $saturday->getRouteKey()])
            ->assertDontSee('Related bookings');

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        $memberUrl = \App\Filament\Member\Resources\Bookings\BookingResource::viewUrl($sunday);

        Livewire::test(TeamsViewBooking::class, ['record' => $saturday->getRouteKey()])
            ->assertSee('Related bookings')
            ->assertSee($sunday->relatedLabel())
            ->assertSeeHtml($memberUrl)
            ->assertSeeHtml('class="invite-panel"');
    }

    public function test_the_member_view_hides_related_bookings_when_there_are_none(): void
    {
        [$manager, $saturday] = $this->weekend();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        Livewire::test(TeamsViewBooking::class, ['record' => $saturday->getRouteKey()])
            ->assertDontSee('Related bookings');
    }

    public function test_a_manager_sees_related_bookings_so_they_can_add_the_first_link(): void
    {
        [$manager, $saturday] = $this->weekend();

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $saturday->getRouteKey()])
            ->assertSee('Related bookings')
            ->assertSeeLivewire(RelatedBookings::class)
            ->assertSee(__('Add'));
    }

    public function test_a_manager_does_not_see_related_bookings_on_a_past_booking_with_nothing_to_add(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $past = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->subDays(10),
            'end_at' => now()->subDays(10)->addHours(2),
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $past->getRouteKey()])
            ->assertDontSee('Related bookings')
            ->assertDontSeeLivewire(RelatedBookings::class);
    }

    public function test_a_past_booking_still_shows_related_bookings_when_linked(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $older = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->subDays(20),
            'end_at' => now()->subDays(20)->addHours(2),
            'group_name' => 'Older camp',
        ]);
        $newerPast = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => now()->subDays(5),
            'end_at' => now()->subDays(5)->addHours(2),
            'group_name' => 'Newer camp',
        ]);
        $older->relate($newerPast);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $older->getRouteKey()])
            ->assertSee('Related bookings')
            ->assertSee($newerPast->relatedLabel());
    }

    public function test_related_bookings_do_not_change_mail_threads_or_uids(): void
    {
        [$manager, $saturday, $sunday] = $this->weekend();
        $attendee = User::factory()->create();
        $saturday->attendees()->attach($attendee->getKey(), [
            'status' => 'accepted',
            'token' => BookingAttendance::generateToken(),
        ]);
        $uid = $saturday->uid;
        $reference = '<'.BookingMailThread::reference($saturday).'>';
        $saturday->relate($sunday);

        $invite = new BookingInviteMail($saturday, $attendee);
        $updated = new BookingChangedMail($saturday, $attendee);

        $this->assertSame($uid, $saturday->uid);
        $this->assertNotSame($saturday->uid, $sunday->uid);
        $this->assertSame($reference, $invite->headers()->referencesString());
        $this->assertSame($reference, $updated->headers()->referencesString());
        $this->assertStringNotContainsString($sunday->getKey(), $invite->headers()->referencesString());
        $this->assertStringStartsWith('booking+'.$saturday->getKey().'@', $saturday->uid);
    }

    /**
     * @return array{0: User, 1: Booking, 2: Booking}
     */
    private function weekend(): array
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $team->addActivity($activity);

        $saturday = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'activity_id' => $activity->getKey(),
            'start_at' => '2026-12-12 09:00:00',
            'end_at' => '2026-12-12 11:00:00',
            'timezone' => 'Europe/London',
            'group_name' => 'Foxes',
        ]);
        $sunday = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'activity_id' => $activity->getKey(),
            'start_at' => '2026-12-13 09:00:00',
            'end_at' => '2026-12-13 11:00:00',
            'timezone' => 'Europe/London',
            'group_name' => 'Cubs',
        ]);

        return [$manager, $saturday, $sunday];
    }
}
