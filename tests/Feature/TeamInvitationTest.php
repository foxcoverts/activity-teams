<?php

namespace Tests\Feature;

use App\Actions\AcceptTeamInvitationAction;
use App\Actions\SendTeamInvitationAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Enums\Section;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Filament\Manage\Resources\Members\Pages\EditMember;
use App\Filament\Manage\Resources\Members\Pages\ListMembers;
use App\Filament\Manage\Resources\Members\Pages\ViewMember;
use App\Livewire\AcceptInvitation;
use App\Models\Booking;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationDeclinedNotification;
use App\Notifications\TeamInvitationNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * US-59: managers add named people and invite them by email.
 */
class TeamInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->team = Team::factory()->create();
        $this->manager = User::factory()->create();
        $this->team->grant($this->manager, MembershipRole::Manager);
    }

    private function invite(string $email = 'new@example.com', MembershipRole $role = MembershipRole::Member): User
    {
        return app(SendTeamInvitationAction::class)->add($this->manager, $this->team, 'Sam Placeholder', $email, $role, Section::Scout);
    }

    private function acceptUrl(TeamInvitation $invitation): string
    {
        return URL::temporarySignedRoute('invitation.show', $invitation->expires_at, [
            'invitation' => $invitation,
            'token' => $invitation->token,
        ]);
    }

    public function test_adding_a_person_creates_a_placeholder_in_the_team_and_emails_the_invite(): void
    {
        $placeholder = $this->invite('new@example.com', MembershipRole::Manager);

        $this->assertTrue($placeholder->isPlaceholder());
        $this->assertNull($placeholder->password);
        $this->assertSame(InvitationStatus::Invited, $placeholder->invitationStatus());
        $this->assertSame(MembershipRole::Manager, $this->team->memberships()->where('user_id', $placeholder->id)->first()->role);
        $this->assertSame('new@example.com', $placeholder->invitation->email);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            TeamInvitationNotification::class,
            fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === ['new@example.com' => $placeholder->name],
        );
    }

    public function test_only_a_team_manager_can_invite(): void
    {
        $member = User::factory()->create();
        $this->team->grant($member, MembershipRole::Member);

        $this->expectException(AuthorizationException::class);

        app(SendTeamInvitationAction::class)->add($member, $this->team, 'Sam', 'x@example.com', MembershipRole::Member, Section::Adult);
    }

    public function test_a_system_admin_can_invite_without_managing_the_team(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($admin);
        Filament::setTenant($this->team);

        Livewire::test(ListMembers::class)
            ->assertActionVisible('add')
            ->callAction('add', ['name' => 'Sam', 'email' => 'admin-invited@example.com', 'role' => 'member', 'section' => 'adult'])
            ->assertNotified('Member added and invitation sent');

        $placeholder = User::query()->where('name', 'Sam')->firstOrFail();
        $this->assertTrue($admin->canInviteTo($this->team));
        $this->assertSame('admin-invited@example.com', $placeholder->invitation->email);
    }

    public function test_an_oversight_account_cannot_invite(): void
    {
        $oversight = User::factory()->create(['oversight' => true]);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($oversight);
        Filament::setTenant($this->team);

        Livewire::test(ListMembers::class)
            ->assertActionHidden('add')
            ->assertActionHidden(TestAction::make('changeRole')->table($this->manager))
            ->assertActionHidden(TestAction::make('leave')->table($this->manager));

        $this->expectException(AuthorizationException::class);
        app(SendTeamInvitationAction::class)->add($oversight, $this->team, 'Sam', 'x@example.com', MembershipRole::Member, Section::Adult);
    }

    public function test_an_email_belonging_to_a_suspended_account_is_refused_and_says_why(): void
    {
        User::factory()->create(['email' => 'gone@example.com', 'suspended' => true]);

        try {
            $this->invite('gone@example.com');
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('suspended', $exception->errors()['email'][0]);
        }

        $this->assertSame(0, User::query()->where('name', 'Sam Placeholder')->count());
        Notification::assertNothingSent();
    }

    public function test_an_email_already_in_the_team_is_refused(): void
    {
        $member = User::factory()->create(['email' => 'there@example.com']);
        $this->team->grant($member, MembershipRole::Member);

        $this->expectException(ValidationException::class);

        $this->invite('there@example.com');
    }

    public function test_an_existing_account_outside_the_team_can_be_invited_without_being_revealed(): void
    {
        User::factory()->create(['email' => 'elsewhere@example.com']);

        $placeholder = $this->invite('elsewhere@example.com');

        $this->assertTrue($placeholder->isPlaceholder());
        $this->assertSame(InvitationStatus::Invited, $placeholder->invitationStatus());
    }

    public function test_resending_replaces_the_link_and_can_correct_the_address(): void
    {
        $placeholder = $this->invite('typo@example.com');
        $oldToken = $placeholder->invitation->token;

        app(SendTeamInvitationAction::class)->send($this->manager, $this->team, $placeholder, 'right@example.com');

        $invitation = $placeholder->invitation()->first();
        $this->assertNotSame($oldToken, $invitation->token);
        $this->assertSame('right@example.com', $invitation->email);
        $this->assertDatabaseCount('team_invitations', 1);
    }

    public function test_a_declined_person_can_be_invited_again(): void
    {
        $placeholder = $this->invite();
        app(AcceptTeamInvitationAction::class)->decline($placeholder->invitation);
        $this->assertSame(InvitationStatus::Declined, $placeholder->fresh()->invitationStatus());

        app(SendTeamInvitationAction::class)->send($this->manager, $this->team, $placeholder->fresh(), 'other@example.com');

        $this->assertSame(InvitationStatus::Invited, $placeholder->fresh()->invitationStatus());
    }

    public function test_revoking_stops_the_invitation_being_used(): void
    {
        $placeholder = $this->invite();

        app(SendTeamInvitationAction::class)->revoke($this->manager, $this->team, $placeholder);

        $this->assertSame(InvitationStatus::NotInvited, $placeholder->fresh()->invitationStatus());

        $this->expectException(ValidationException::class);
        app(AcceptTeamInvitationAction::class)->decline($placeholder->invitation()->first());
    }

    public function test_an_expired_invitation_is_reported_as_expired_and_cannot_be_accepted(): void
    {
        $invitation = TeamInvitation::factory()->expired()->create(['team_id' => $this->team->id]);

        $this->assertSame(InvitationStatus::Expired, $invitation->user->invitationStatus());

        $this->expectException(ValidationException::class);
        app(AcceptTeamInvitationAction::class)->createAccount($invitation, ['email' => 'a@example.com', 'password' => 'secret-pass-1', 'timezone' => 'Europe/London']);
    }

    public function test_a_person_without_an_email_cannot_have_a_password(): void
    {
        $user = User::factory()->create(['email' => 'p@example.com', 'password' => 'secret']);
        $user->forceFill(['email' => null])->save();

        $this->assertNull($user->fresh()->password);

        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user->id)->update(['password' => 'x']);
    }

    public function test_creating_an_account_turns_the_placeholder_into_it_and_keeps_its_attendance(): void
    {
        $placeholder = $this->invite('new@example.com');
        $booking = Booking::factory()->create(['team_id' => $this->team->id]);
        $booking->attendees()->attach($placeholder->id, ['status' => BookingAttendeeStatus::Accepted->value]);

        $user = app(AcceptTeamInvitationAction::class)->createAccount($placeholder->invitation, [
            'email' => 'new@example.com',
            'password' => 'secret-pass-1',
            'timezone' => 'Europe/Paris',
            'phone' => '07700 900123',
            'emergency_name' => 'Pat',
            'emergency_phone' => '07700 900456',
        ]);

        $this->assertTrue($user->is($placeholder));
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('Europe/Paris', $user->timezone->getName());
        $this->assertSame('Pat', $user->emergency_name);
        $this->assertDatabaseCount('team_invitations', 0);
        $this->assertSame(BookingAttendeeStatus::Accepted, $booking->attendees()->find($user->id)->attendance->status);
    }

    public function test_changing_the_email_on_signup_leaves_it_unverified(): void
    {
        $placeholder = $this->invite('invited@example.com');

        $user = app(AcceptTeamInvitationAction::class)->createAccount($placeholder->invitation, [
            'email' => 'mine@example.com',
            'password' => 'secret-pass-1',
            'timezone' => 'Europe/London',
        ]);

        $this->assertSame('mine@example.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_a_signup_email_already_in_use_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@example.com', 'suspended' => true]);
        $placeholder = $this->invite('invited@example.com');

        $this->expectException(ValidationException::class);

        app(AcceptTeamInvitationAction::class)->createAccount($placeholder->invitation, [
            'email' => 'taken@example.com',
            'password' => 'secret-pass-1',
            'timezone' => 'Europe/London',
        ]);
    }

    public function test_claiming_with_an_existing_account_merges_the_placeholder_into_it(): void
    {
        $placeholder = $this->invite('invited@example.com', MembershipRole::Manager);
        $account = User::factory()->create();
        $booking = Booking::factory()->create(['team_id' => $this->team->id, 'lead_instructor_id' => $placeholder->id]);
        $booking->attendees()->attach($placeholder->id, ['status' => BookingAttendeeStatus::Tentative->value]);

        app(AcceptTeamInvitationAction::class)->claimWith($placeholder->invitation, $account);

        $this->assertNull(User::query()->find($placeholder->id));
        $this->assertSame(MembershipRole::Manager, $this->team->memberships()->where('user_id', $account->id)->first()->role);
        $this->assertSame($account->id, $booking->fresh()->lead_instructor_id);
        $this->assertSame(BookingAttendeeStatus::Tentative, $booking->attendees()->find($account->id)->attendance->status);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function attendanceMergeCases(): array
    {
        return [
            'same answer' => ['accepted', 'accepted', 'accepted'],
            'placeholder unanswered' => ['needs-action', 'accepted', 'accepted'],
            'account unanswered' => ['declined', 'needs-action', 'declined'],
            'different answers' => ['accepted', 'declined', 'needs-action'],
        ];
    }

    #[DataProvider('attendanceMergeCases')]
    public function test_attendance_on_the_same_booking_is_reconciled(string $placeholderStatus, string $accountStatus, string $expected): void
    {
        $placeholder = $this->invite();
        $account = User::factory()->create();
        $booking = Booking::factory()->create(['team_id' => $this->team->id]);
        $booking->attendees()->attach($placeholder->id, ['status' => $placeholderStatus]);
        $booking->attendees()->attach($account->id, ['status' => $accountStatus]);

        app(AcceptTeamInvitationAction::class)->claimWith($placeholder->invitation, $account);

        $this->assertSame($expected, $booking->attendees()->find($account->id)->attendance->status->value);
        $this->assertSame(1, $booking->attendees()->count());
    }

    public function test_an_existing_manager_keeps_the_higher_role_when_claiming_a_member_placeholder(): void
    {
        $placeholder = $this->invite('invited@example.com', MembershipRole::Member);

        app(AcceptTeamInvitationAction::class)->claimWith($placeholder->invitation, $this->manager);

        $this->assertSame(MembershipRole::Manager, $this->team->memberships()->where('user_id', $this->manager->id)->first()->role);
    }

    public function test_a_suspended_account_cannot_claim_an_invitation(): void
    {
        $placeholder = $this->invite();
        $account = User::factory()->create(['suspended' => true]);

        $this->expectException(ValidationException::class);

        app(AcceptTeamInvitationAction::class)->claimWith($placeholder->invitation, $account);
    }

    public function test_the_invitation_page_requires_a_valid_signature_and_token(): void
    {
        $placeholder = $this->invite();
        $invitation = $placeholder->invitation;

        $this->get(route('invitation.show', ['invitation' => $invitation, 'token' => $invitation->token]))->assertForbidden();

        $this->get(URL::temporarySignedRoute('invitation.show', $invitation->expires_at, ['invitation' => $invitation, 'token' => 'wrong']))->assertForbidden();

        $this->get($this->acceptUrl($invitation))->assertOk()->assertSee('Create an account');
    }

    public function test_a_replaced_link_stops_working(): void
    {
        $placeholder = $this->invite();
        $oldUrl = $this->acceptUrl($placeholder->invitation);

        app(SendTeamInvitationAction::class)->send($this->manager, $this->team, $placeholder, 'new@example.com');

        $this->get($oldUrl)->assertForbidden();
    }

    public function test_the_page_creates_an_account_and_signs_in(): void
    {
        $placeholder = $this->invite('new@example.com');
        $url = $this->acceptUrl($placeholder->invitation);

        Livewire::withQueryParams(['token' => $placeholder->invitation->token])
            ->test(AcceptInvitation::class, ['invitation' => $placeholder->invitation])
            ->set('password', 'secret-pass-1')
            ->set('password_confirmation', 'secret-pass-1')
            ->set('timezone', 'Europe/London')
            ->call('createAccount')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($placeholder->fresh());
        $this->assertNotEmpty($url);
    }

    public function test_the_page_can_decline_the_invitation(): void
    {
        $placeholder = $this->invite();

        Livewire::withQueryParams(['token' => $placeholder->invitation->token])
            ->test(AcceptInvitation::class, ['invitation' => $placeholder->invitation])
            ->call('decline')
            ->assertSee('declined');

        $this->assertSame(InvitationStatus::Declined, $placeholder->fresh()->invitationStatus());
    }

    public function test_declining_notifies_all_current_active_verified_team_managers(): void
    {
        $placeholder = $this->invite();
        $anotherManager = User::factory()->create();
        $unverifiedManager = User::factory()->unverified()->create();
        $inactiveManager = User::factory()->inactive()->create();
        $suspendedManager = User::factory()->create(['suspended' => true]);
        $this->team->grant($anotherManager, MembershipRole::Manager);
        $this->team->grant($unverifiedManager, MembershipRole::Manager);
        $this->team->grant($inactiveManager, MembershipRole::Manager);
        $this->team->grant($suspendedManager, MembershipRole::Manager);

        app(AcceptTeamInvitationAction::class)->decline($placeholder->invitation);

        Notification::assertSentTo($this->manager, TeamInvitationDeclinedNotification::class);
        Notification::assertSentTo($anotherManager, TeamInvitationDeclinedNotification::class);
        Notification::assertNotSentTo($unverifiedManager, TeamInvitationDeclinedNotification::class);
        Notification::assertNotSentTo($inactiveManager, TeamInvitationDeclinedNotification::class);
        Notification::assertNotSentTo($suspendedManager, TeamInvitationDeclinedNotification::class);

        $notification = Notification::sent($this->manager, TeamInvitationDeclinedNotification::class)->first();
        $mail = $notification->toMail($this->manager);
        $this->assertSame('Invitation declined', $mail->subject);
        $this->assertStringContainsString('Sam Placeholder declined', implode(' ', $mail->introLines));
        $this->assertStringNotContainsString('account', implode(' ', $mail->introLines));
    }

    public function test_a_signed_in_user_can_accept_with_their_existing_account(): void
    {
        $placeholder = $this->invite('invited@example.com');
        $account = User::factory()->create();

        $this->actingAs($account);

        Livewire::withQueryParams(['token' => $placeholder->invitation->token])
            ->test(AcceptInvitation::class, ['invitation' => $placeholder->invitation])
            ->call('useMyAccount');

        $this->assertTrue($account->isCurrentMemberOf($this->team));
    }

    public function test_a_signed_in_user_can_sign_out_and_return_to_create_an_account(): void
    {
        $placeholder = $this->invite();
        $account = User::factory()->create();
        $this->actingAs($account);
        $url = $this->acceptUrl($placeholder->invitation);
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->withSession(['session_marker' => 'before-logout']);
        $response = $this->get($url);
        $response->assertOk()->assertSee('Sign out and create a new account');
        $this->assertStringContainsString($query['signature'], html_entity_decode($response->getContent()));
        $intendedUrl = (string) session('url.intended');
        $intendedQuery = [];
        parse_str((string) parse_url($intendedUrl, PHP_URL_QUERY), $intendedQuery);
        ksort($query);
        ksort($intendedQuery);
        $this->assertSame(parse_url($url, PHP_URL_PATH), parse_url($intendedUrl, PHP_URL_PATH));
        $this->assertSame($query, $intendedQuery);

        $component = Livewire::withQueryParams($query)
            ->test(AcceptInvitation::class, ['invitation' => $placeholder->invitation]);
        $component->call('signOutAndCreateAccount')->assertRedirect();

        $this->assertGuest();
        $this->assertNull(session('session_marker'));
    }

    private function actAsManager(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($this->manager);
        Filament::setTenant($this->team);
    }

    public function test_the_members_list_adds_a_person_and_shows_their_invitation_status(): void
    {
        $this->actAsManager();

        Livewire::test(ListMembers::class)
            ->callAction('add', ['name' => 'Sam Placeholder', 'email' => 'new@example.com', 'role' => 'member', 'section' => 'scout'])
            ->assertNotified('Member added and invitation sent');

        $placeholder = User::query()->where('name', 'Sam Placeholder')->firstOrFail();

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$placeholder])
            ->assertSee('Invited')
            ->assertDontSee('new@example.com');
    }

    public function test_the_members_list_tells_the_manager_when_the_email_belongs_to_a_suspended_account(): void
    {
        User::factory()->create(['email' => 'gone@example.com', 'suspended' => true]);
        $this->actAsManager();

        Livewire::test(ListMembers::class)
            ->callAction('add', ['name' => 'Sam', 'email' => 'gone@example.com', 'role' => 'member', 'section' => 'scout'])
            ->assertHasActionErrors(['email']);

        $this->assertSame(0, User::query()->where('name', 'Sam')->count());
    }

    public function test_the_members_list_shows_an_email_already_in_the_team_as_a_form_error(): void
    {
        $member = User::factory()->create(['email' => 'there@example.com']);
        $this->team->grant($member, MembershipRole::Member);
        $this->actAsManager();

        Livewire::test(ListMembers::class)
            ->callAction('add', ['name' => 'Sam', 'email' => 'there@example.com', 'role' => 'member', 'section' => 'scout'])
            ->assertHasActionErrors(['email'])
            ->assertNotNotified();

        $this->assertSame(0, User::query()->where('name', 'Sam')->count());
    }

    public function test_resending_an_invitation_shows_an_email_already_in_the_team_as_a_form_error(): void
    {
        $placeholder = $this->invite();
        $member = User::factory()->create(['email' => 'there@example.com']);
        $this->team->grant($member, MembershipRole::Member);
        $this->actAsManager();

        Livewire::test(ListMembers::class)
            ->callAction(TestAction::make('sendInvitation')->table($placeholder), ['email' => 'there@example.com'])
            ->assertHasActionErrors(['email'])
            ->assertNotNotified();
    }

    public function test_member_actions_are_available_on_the_view_page(): void
    {
        $placeholder = $this->invite();
        $this->actAsManager();

        Livewire::test(ViewMember::class, ['record' => $placeholder->getRouteKey()])
            ->assertActionVisible('changeRole')
            ->assertActionVisible('leave')
            ->assertSee('Invitation');
    }

    public function test_changing_role_from_the_view_page_updates_and_refreshes_the_record(): void
    {
        $member = User::factory()->create();
        $this->team->grant($member, MembershipRole::Member);
        $this->actAsManager();

        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction('changeRole', ['role' => 'manager'])
            ->assertRedirect(MemberResource::getUrl('view', ['record' => $member]));

        $this->assertSame(
            MembershipRole::Manager,
            $this->team->memberships()->where('user_id', $member->getKey())->firstOrFail()->role,
        );
    }

    public function test_changing_role_from_the_edit_page_more_actions_menu_updates_membership(): void
    {
        $member = User::factory()->create();
        $this->team->grant($member, MembershipRole::Member);
        $this->actAsManager();

        Livewire::test(EditMember::class, ['record' => $member->getRouteKey()])
            ->assertSee('More actions')
            ->assertActionVisible('changeRole')
            ->callAction('changeRole', ['role' => 'manager']);

        $this->assertSame(
            MembershipRole::Manager,
            $this->team->memberships()->where('user_id', $member->getKey())->firstOrFail()->role,
        );
    }

    public function test_removing_a_member_from_the_view_page_returns_to_the_members_list(): void
    {
        $member = User::factory()->create();
        $this->team->grant($member, MembershipRole::Member);
        $this->actAsManager();

        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction('leave')
            ->assertRedirect(MemberResource::getUrl('index'));

        $this->assertNotNull($this->team->memberships()->where('user_id', $member->getKey())->firstOrFail()->left_at);
    }

    public function test_placeholder_rows_offer_invitation_actions_and_active_members_do_not(): void
    {
        $placeholder = $this->invite();
        $this->actAsManager();

        Livewire::test(ListMembers::class)
            ->assertActionVisible(TestAction::make('sendInvitation')->table($placeholder))
            ->assertActionVisible(TestAction::make('revokeInvitation')->table($placeholder))
            ->assertActionHidden(TestAction::make('sendInvitation')->table($this->manager))
            ->callAction(TestAction::make('revokeInvitation')->table($placeholder))
            ->assertActionHidden(TestAction::make('revokeInvitation')->table($placeholder));

        $this->assertSame(InvitationStatus::NotInvited, $placeholder->fresh()->invitationStatus());
    }

    public function test_an_account_created_with_a_changed_email_is_held_at_the_verification_prompt(): void
    {
        $placeholder = $this->invite('invited@example.com');

        $user = app(AcceptTeamInvitationAction::class)->createAccount($placeholder->invitation, [
            'email' => 'mine@example.com',
            'password' => 'secret-pass-1',
            'timezone' => 'Europe/London',
        ]);

        $this->actingAs($user)->get('/')->assertRedirect();
        $this->assertStringContainsString('verif', (string) $this->actingAs($user)->get('/')->headers->get('Location'));
    }

    public function test_inviting_and_revoking_are_recorded_in_the_activity_log(): void
    {
        $placeholder = $this->invite();
        app(SendTeamInvitationAction::class)->revoke($this->manager, $this->team, $placeholder);

        $this->assertDatabaseHas('activity_log', ['description' => 'Invited Sam Placeholder', 'causer_id' => $this->manager->id]);
        $this->assertDatabaseHas('activity_log', ['description' => 'Revoked the invitation for Sam Placeholder']);
    }
}
