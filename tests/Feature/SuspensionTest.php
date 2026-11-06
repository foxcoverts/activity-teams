<?php

namespace Tests\Feature;

use App\Actions\OverrideAttendanceAction;
use App\Actions\UpdateBookingStatusAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Manage\Actions\SuspensionActions;
use App\Filament\Member\Resources\Bookings\BookingResource as MemberBookingResource;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking as MemberViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager as MemberAttendeesRelationManager;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Filament\Teams\Resources\Members\Pages\EditMember;
use App\Filament\Teams\Resources\Members\Pages\ViewMember;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Notifications\LeadNeededNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * US-48: suspension is system-wide, silent, and kept apart from leaving a team.
 */
class SuspensionTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $manager;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create(['name' => 'Climbing']);
        $this->manager = User::factory()->create();
        $this->team->grant($this->manager, MembershipRole::Manager);
        $this->member = User::factory()->create(['name' => 'Alex Member']);
        $this->team->grant($this->member, MembershipRole::Member);

        Filament::setCurrentPanel('teams');
    }

    public function test_a_manager_suspends_and_restores_a_member_from_the_edit_page(): void
    {
        Mail::fake();
        Notification::fake();
        $booking = Booking::factory()->confirmed()->create(['team_id' => $this->team->getKey()]);
        $booking->attendees()->attach($this->member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        $this->actingAs($this->manager);

        Livewire::test(EditMember::class, ['record' => $this->member->getKey()])
            ->assertActionVisible(TestAction::make('suspend'))
            ->assertActionHidden(TestAction::make('restore'))
            ->callAction(TestAction::make('suspend'))
            ->assertNotified();

        $this->member->refresh();
        $this->assertTrue($this->member->isSuspended());
        $this->assertSame(
            'accepted',
            $booking->attendees()->find($this->member)->attendance->status->value,
        );
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();

        Livewire::test(EditMember::class, ['record' => $this->member->getKey()])
            ->assertActionHidden(TestAction::make('suspend'))
            ->assertActionVisible(TestAction::make('restore'))
            ->callAction(TestAction::make('restore'))
            ->assertNotified();

        $this->assertFalse($this->member->fresh()->isSuspended());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    public function test_suspending_is_separate_from_leaving_and_keeps_the_record(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(EditMember::class, ['record' => $this->member->getKey()])
            ->callAction(TestAction::make('suspend'));

        $membership = $this->member->memberships()->where('team_id', $this->team->getKey())->firstOrFail();

        $this->assertNull($membership->left_at);
        $this->assertSame(MembershipRole::Member, $membership->role);
        $this->assertSame('Alex Member', $this->member->fresh()->name);
    }

    public function test_suspension_is_recorded_in_the_activity_log_with_who_did_it(): void
    {
        $this->actingAs($this->manager);

        Livewire::test(EditMember::class, ['record' => $this->member->getKey()])
            ->callAction(TestAction::make('suspend'));

        $entry = Activity::forSubject($this->member)->latest('id')->firstOrFail();

        $this->assertTrue($entry->causer->is($this->manager));
        $this->assertTrue($entry->attribute_changes['attributes']['suspended']);
    }

    public function test_suspending_unassigns_upcoming_leads_and_returns_confirmed_bookings_to_tentative(): void
    {
        Mail::fake();
        $otherManager = User::factory()->create();
        $this->team->grant($otherManager, MembershipRole::Manager);
        $upcoming = Booking::factory()->confirmed()->create(['team_id' => $this->team->getKey(), 'lead_instructor_id' => $this->member->getKey()]);
        $past = Booking::factory()->confirmed()->create([
            'team_id' => $this->team->getKey(),
            'lead_instructor_id' => $this->member->getKey(),
            'start_at' => now()->subDays(3),
            'end_at' => now()->subDays(3)->addHours(2),
        ]);
        $cancelled = Booking::factory()->cancelled()->create(['team_id' => $this->team->getKey(), 'lead_instructor_id' => $this->member->getKey()]);
        $other = Booking::factory()->confirmed()->create(['team_id' => $this->team->getKey(), 'lead_instructor_id' => $this->manager->getKey()]);

        $this->assertStringContainsString(
            'lead instructor for 1 upcoming booking',
            SuspensionActions::suspend($this->member)->getModalDescription(),
        );

        $this->actingAs($this->manager);
        Livewire::test(EditMember::class, ['record' => $this->member->getKey()])
            ->callAction(TestAction::make('suspend'));

        $this->assertNull($upcoming->fresh()->lead_instructor_id);
        $this->assertTrue($upcoming->fresh()->isTentative());
        $this->assertSame($this->member->getKey(), $past->fresh()->lead_instructor_id);
        $this->assertTrue($past->fresh()->isConfirmed());
        $this->assertSame($this->member->getKey(), $cancelled->fresh()->lead_instructor_id);
        $this->assertSame($this->manager->getKey(), $other->fresh()->lead_instructor_id);
        $this->assertTrue($otherManager->notifications()->where('type', LeadNeededNotification::class)->exists());
        $this->assertFalse($this->manager->notifications()->where('type', LeadNeededNotification::class)->exists());
        $this->assertFalse($this->member->notifications()->where('type', LeadNeededNotification::class)->exists());
        Mail::assertNothingQueued();
    }

    public function test_the_modal_does_not_mention_bookings_when_there_are_none_to_unassign(): void
    {
        $this->assertStringNotContainsString(
            'lead instructor',
            SuspensionActions::suspend($this->member)->getModalDescription(),
        );
    }

    public function test_restoring_a_cancelled_booking_removes_a_suspended_lead(): void
    {
        Mail::fake();
        $suspendedLed = Booking::factory()->cancelled()->create(['team_id' => $this->team->getKey(), 'lead_instructor_id' => $this->member->getKey()]);
        $activeLed = Booking::factory()->cancelled()->create(['team_id' => $this->team->getKey(), 'lead_instructor_id' => $this->manager->getKey()]);
        $this->member->forceFill(['suspended' => true])->save();

        app(UpdateBookingStatusAction::class)->restore($suspendedLed->fresh(), $this->manager);
        app(UpdateBookingStatusAction::class)->restore($activeLed->fresh(), $this->manager);

        $this->assertNull($suspendedLed->fresh()->lead_instructor_id);
        $this->assertTrue($suspendedLed->fresh()->isTentative());
        $this->assertSame($this->manager->getKey(), $activeLed->fresh()->lead_instructor_id);
    }

    public function test_who_may_suspend(): void
    {
        $otherTeam = Team::factory()->create();
        $outsideManager = User::factory()->create();
        $otherTeam->grant($outsideManager, MembershipRole::Manager);
        $plainMember = User::factory()->create();
        $this->team->grant($plainMember, MembershipRole::Member);
        $oversight = User::factory()->oversight()->create();
        $admin = User::factory()->systemAdmin()->create();

        $this->assertTrue($this->manager->can('suspend', $this->member));
        $this->assertTrue($oversight->can('suspend', $this->member));
        $this->assertTrue($admin->can('suspend', $this->member));

        $this->assertFalse($this->manager->can('suspend', $this->manager));
        $this->assertFalse($outsideManager->can('suspend', $this->member));
        $this->assertFalse($plainMember->can('suspend', $this->member));
    }

    public function test_a_team_manager_cannot_suspend_someone_with_a_site_wide_role(): void
    {
        $oversight = User::factory()->oversight()->create();
        $admin = User::factory()->systemAdmin()->create();
        $this->team->grant($oversight, MembershipRole::Member);
        $this->team->grant($admin, MembershipRole::Member);

        $this->assertFalse($this->manager->can('suspend', $oversight));
        $this->assertFalse($this->manager->can('suspend', $admin));
        $this->assertTrue($admin->can('suspend', $oversight));
        $this->assertTrue($oversight->can('suspend', $admin));
    }

    public function test_the_action_is_hidden_from_people_who_cannot_suspend(): void
    {
        $plainMember = User::factory()->systemAdmin()->create();
        $this->team->grant($plainMember, MembershipRole::Member);

        $this->actingAs($this->manager);

        Livewire::test(EditMember::class, ['record' => $this->manager->getKey()])
            ->assertActionHidden(TestAction::make('suspend'));
    }

    public function test_the_global_user_edit_page_offers_suspension_to_a_system_admin(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $this->member->getKey()])
            ->callAction(TestAction::make('suspend'))
            ->assertNotified();

        $this->assertTrue($this->member->fresh()->isSuspended());
    }

    public function test_the_modal_warns_when_a_team_would_have_no_active_manager(): void
    {
        $backup = User::factory()->create();
        $other = Team::factory()->create(['name' => 'Archery']);
        $other->grant($this->manager, MembershipRole::Manager);
        $other->grant($backup, MembershipRole::Manager);
        $admin = User::factory()->systemAdmin()->create();

        $this->assertSame(['Climbing'], $this->manager->teamsLeftWithoutActiveManager()->pluck('name')->all());

        $backup->forceFill(['suspended' => true])->save();

        $this->assertEqualsCanonicalizing(
            ['Climbing', 'Archery'],
            $this->manager->teamsLeftWithoutActiveManager()->pluck('name')->all(),
        );

        $this->actingAs($admin);

        $this->assertStringContainsString(
            'would have no active manager',
            SuspensionActions::suspend($this->manager)->getModalDescription(),
        );
        $this->assertStringNotContainsString(
            'would have no active manager',
            SuspensionActions::suspend($this->member)->getModalDescription(),
        );
    }

    public function test_a_suspended_person_has_no_access_even_when_already_signed_in(): void
    {
        $booking = Booking::factory()->confirmed()->create(['team_id' => $this->team->getKey()]);
        $this->actingAs($this->member);

        $this->get(MemberBookingResource::viewUrl($booking))->assertOk();

        $this->member->forceFill(['suspended' => true])->save();

        $this->get(MemberBookingResource::viewUrl($booking))->assertForbidden();
        $this->get(MemberBookingResource::getUrl('index', panel: 'member'))->assertForbidden();
    }

    public function test_a_suspended_manager_loses_manager_access(): void
    {
        $this->manager->forceFill(['suspended' => true])->save();

        $this->actingAs($this->manager);

        $this->get('/manage')->assertForbidden();
        $this->assertFalse($this->manager->manages($this->team));
        $this->assertFalse($this->manager->can('suspend', $this->member));
    }

    public function test_managers_can_still_view_edit_and_remove_a_suspended_member_silently(): void
    {
        Mail::fake();
        Notification::fake();
        $booking = Booking::factory()->confirmed()->create(['team_id' => $this->team->getKey()]);
        $booking->attendees()->attach($this->member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);
        $this->member->forceFill(['suspended' => true])->save();

        $this->actingAs($this->manager);

        $this->assertTrue($this->manager->can('view', $this->member));
        $this->assertTrue($this->manager->can('update', $this->member));

        Livewire::test(ViewMember::class, ['record' => $this->member->getKey()])
            ->assertSee('Suspended');

        Livewire::test(EditMember::class, ['record' => $this->member->getKey()])
            ->fillForm(['name' => 'Alex Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Alex Renamed', $this->member->fresh()->name);

        $booking->attendees()->detach($this->member->getKey());
        $this->team->recordLeft($this->member);

        $this->assertFalse($booking->attendees()->whereKey($this->member->getKey())->exists());
        $this->assertNotNull($this->member->memberships()->first()->left_at);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    public function test_a_manager_override_of_attendance_sends_nothing_to_a_suspended_member(): void
    {
        Mail::fake();
        $booking = Booking::factory()->confirmed()->create(['team_id' => $this->team->getKey()]);
        $booking->attendees()->attach($this->member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);
        $this->member->forceFill(['suspended' => true, 'email_verified_at' => now()])->save();

        (new OverrideAttendanceAction)($booking, $this->member, $this->manager, BookingAttendeeStatus::Declined);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_only_managers_see_a_suspended_member_on_a_roster(): void
    {
        $booking = Booking::factory()->confirmed()->create(['team_id' => $this->team->getKey()]);
        $booking->attendees()->attach($this->member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);
        $teammate = User::factory()->create();
        $this->team->grant($teammate, MembershipRole::Member);
        $this->member->forceFill(['suspended' => true])->save();

        Filament::setCurrentPanel('teams');
        $this->actingAs($teammate);
        Livewire::test(MemberAttendeesRelationManager::class, ['ownerRecord' => $booking, 'pageClass' => MemberViewBooking::class])
            ->assertDontSee('Alex Member');

        $this->actingAs($this->manager);
        Livewire::test(MemberAttendeesRelationManager::class, ['ownerRecord' => $booking, 'pageClass' => MemberViewBooking::class])
            ->assertSee('Alex Member');
    }

    public function test_the_member_list_does_not_offer_suspension(): void
    {
        $this->actingAs($this->manager);

        $this->get(MemberResource::getUrl('index', panel: 'teams', tenant: $this->team))
            ->assertOk()
            ->assertDontSee('Suspend');
    }
}
