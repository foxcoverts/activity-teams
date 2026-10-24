<?php

namespace Tests\Feature;

use App\Actions\AcceptTeamInvitationAction;
use App\Actions\SendTeamInvitationAction;
use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Enums\Section;
use App\Filament\Manage\Resources\Users\Pages\ListUsers;
use App\Filament\Manage\Resources\Users\Pages\ViewUser;
use App\Filament\Manage\Resources\Users\UserResource;
use App\Livewire\AcceptInvitation;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationDeclinedNotification;
use App\Notifications\TeamInvitationNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * US-60: system admins invite oversight and system admins without a team.
 */
class AdminInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->team = Team::factory()->create();
        $this->admin = User::factory()->create(['is_system_admin' => true]);
    }

    /**
     * @return array<string, array{0: bool, 1: bool}>
     */
    public static function accessFlagCases(): array
    {
        return [
            'oversight' => [true, false],
            'system admin' => [false, true],
            'both' => [true, true],
        ];
    }

    #[DataProvider('accessFlagCases')]
    public function test_a_system_admin_can_invite_site_access_without_a_team(bool $oversight, bool $systemAdmin): void
    {
        $placeholder = $this->inviteAdmin('new@example.com', $oversight, $systemAdmin);

        $this->assertTrue($placeholder->isPlaceholder());
        $this->assertSame($oversight, $placeholder->oversight);
        $this->assertSame($systemAdmin, $placeholder->is_system_admin);
        $this->assertFalse($placeholder->teams()->exists());
        $this->assertTrue($placeholder->invitation->isSiteInvitation());
        $this->assertSame(InvitationStatus::Invited, $placeholder->invitationStatus());

        Notification::assertSentTo(
            new AnonymousNotifiable,
            TeamInvitationNotification::class,
            fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === ['new@example.com' => $placeholder->name],
        );
    }

    public function test_inviting_without_oversight_or_system_admin_is_refused(): void
    {
        try {
            $this->inviteAdmin('new@example.com', false, false);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('oversight', $exception->errors());
        }

        $this->assertSame(0, User::query()->where('name', 'Sam Placeholder')->count());
        Notification::assertNothingSent();
    }

    public function test_oversight_cannot_send_a_site_access_invitation(): void
    {
        $oversight = User::factory()->create(['oversight' => true]);

        $this->expectException(AuthorizationException::class);

        app(SendTeamInvitationAction::class)->addAdmin($oversight, 'Sam', 'x@example.com', true, false, Section::Adult);
    }

    public function test_a_team_manager_cannot_send_a_site_access_invitation(): void
    {
        $manager = User::factory()->create();
        $this->team->grant($manager, MembershipRole::Manager);

        $this->expectException(AuthorizationException::class);

        app(SendTeamInvitationAction::class)->addAdmin($manager, 'Sam', 'x@example.com', false, true, Section::Adult);
    }

    public function test_a_suspended_email_is_refused_and_named_as_suspended(): void
    {
        User::factory()->create(['email' => 'gone@example.com', 'suspended' => true]);

        try {
            $this->inviteAdmin('gone@example.com');
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('suspended', $exception->errors()['email'][0]);
        }

        $this->assertSame(0, User::query()->where('name', 'Sam Placeholder')->count());
    }

    public function test_an_existing_account_email_is_refused(): void
    {
        User::factory()->create(['email' => 'there@example.com']);

        try {
            $this->inviteAdmin('there@example.com');
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('already has an account', $exception->errors()['email'][0]);
        }

        $this->assertSame(0, User::query()->where('name', 'Sam Placeholder')->count());
    }

    public function test_declining_notifies_active_verified_system_admins_only(): void
    {
        $placeholder = $this->inviteAdmin();
        $anotherAdmin = User::factory()->create(['is_system_admin' => true]);
        $unverifiedAdmin = User::factory()->unverified()->create(['is_system_admin' => true]);
        $inactiveAdmin = User::factory()->inactive()->create(['is_system_admin' => true]);
        $suspendedAdmin = User::factory()->create(['is_system_admin' => true, 'suspended' => true]);
        $oversight = User::factory()->create(['oversight' => true]);
        $manager = User::factory()->create();
        $this->team->grant($manager, MembershipRole::Manager);

        app(AcceptTeamInvitationAction::class)->decline($placeholder->invitation);

        Notification::assertSentTo($this->admin, TeamInvitationDeclinedNotification::class);
        Notification::assertSentTo($anotherAdmin, TeamInvitationDeclinedNotification::class);
        Notification::assertNotSentTo($unverifiedAdmin, TeamInvitationDeclinedNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, TeamInvitationDeclinedNotification::class);
        Notification::assertNotSentTo($suspendedAdmin, TeamInvitationDeclinedNotification::class);
        Notification::assertNotSentTo($oversight, TeamInvitationDeclinedNotification::class);
        Notification::assertNotSentTo($manager, TeamInvitationDeclinedNotification::class);

        $notification = Notification::sent($this->admin, TeamInvitationDeclinedNotification::class)->first();
        $mail = $notification->toMail($this->admin);
        $this->assertSame('Invitation declined', $mail->subject);
        $this->assertStringContainsString('Sam Placeholder declined', implode(' ', $mail->introLines));
        $this->assertStringContainsString('system administrator', implode(' ', $mail->introLines));
        $this->assertStringNotContainsString($this->team->name, implode(' ', $mail->introLines));
    }

    public function test_declining_a_team_invitation_does_not_notify_system_admins_who_do_not_manage_the_team(): void
    {
        $manager = User::factory()->create();
        $this->team->grant($manager, MembershipRole::Manager);
        $placeholder = app(SendTeamInvitationAction::class)->add($manager, $this->team, 'Sam Placeholder', 'new@example.com', MembershipRole::Member, Section::Adult);

        app(AcceptTeamInvitationAction::class)->decline($placeholder->invitation);

        Notification::assertSentTo($manager, TeamInvitationDeclinedNotification::class);
        Notification::assertNotSentTo($this->admin, TeamInvitationDeclinedNotification::class);
    }

    public function test_creating_an_account_keeps_site_flags_and_does_not_grant_a_team(): void
    {
        $placeholder = $this->inviteAdmin('new@example.com', true, true);

        $user = app(AcceptTeamInvitationAction::class)->createAccount($placeholder->invitation, [
            'email' => 'new@example.com',
            'password' => 'secret-pass-1',
            'timezone' => 'Europe/London',
        ]);

        $this->assertTrue($user->is($placeholder));
        $this->assertTrue($user->hasOversight());
        $this->assertTrue($user->isSystemAdmin());
        $this->assertFalse($user->teams()->exists());
        $this->assertDatabaseCount('team_invitations', 0);
    }

    public function test_claiming_with_an_existing_account_ors_flags_and_keeps_memberships(): void
    {
        $placeholder = $this->inviteAdmin('invited@example.com', true, true);
        $account = User::factory()->create(['oversight' => false, 'is_system_admin' => false]);
        $this->team->grant($account, MembershipRole::Member);

        app(AcceptTeamInvitationAction::class)->claimWith($placeholder->invitation, $account);

        $this->assertNull(User::query()->find($placeholder->id));
        $this->assertTrue($account->fresh()->hasOversight());
        $this->assertTrue($account->fresh()->isSystemAdmin());
        $this->assertTrue($account->isCurrentMemberOf($this->team));
        $this->assertSame(MembershipRole::Member, $this->team->memberships()->where('user_id', $account->id)->first()->role);
    }

    public function test_the_accept_page_does_not_mention_a_team(): void
    {
        $placeholder = $this->inviteAdmin();
        $invitation = $placeholder->invitation;

        $this->get($this->acceptUrl($invitation))
            ->assertOk()
            ->assertSee('system administrator')
            ->assertDontSee('join '.$this->team->name)
            ->assertDontSee('team manager');
    }

    public function test_an_expired_site_invitation_tells_the_invitee_to_ask_a_system_administrator(): void
    {
        $invitation = TeamInvitation::factory()->siteAccess()->expired()->create();

        $this->get(URL::temporarySignedRoute('invitation.show', now()->addHour(), [
            'invitation' => $invitation,
            'token' => $invitation->token,
        ]))
            ->assertOk()
            ->assertSee('Ask a system administrator')
            ->assertDontSee('team manager');
    }

    public function test_the_users_list_adds_a_person_and_hides_the_invited_email(): void
    {
        $this->actAsAdmin();

        Livewire::test(ListUsers::class)
            ->assertActionVisible('add')
            ->callAction('add', [
                'name' => 'Sam Placeholder',
                'email' => 'new@example.com',
                'section' => 'adult',
                'oversight' => false,
                'is_system_admin' => true,
            ])
            ->assertNotified('Person added and invitation sent');

        $placeholder = User::query()->where('name', 'Sam Placeholder')->firstOrFail();

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$placeholder])
            ->assertSee('Invited')
            ->assertDontSee('new@example.com');
    }

    public function test_oversight_does_not_see_the_users_add_action(): void
    {
        $oversight = User::factory()->create(['oversight' => true]);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($oversight);

        Livewire::test(ListUsers::class)
            ->assertActionHidden('add');
    }

    public function test_placeholder_rows_offer_invitation_actions_and_active_users_do_not(): void
    {
        $placeholder = $this->inviteAdmin();
        $this->actAsAdmin();

        Livewire::test(ListUsers::class)
            ->assertActionVisible(TestAction::make('sendInvitation')->table($placeholder))
            ->assertActionVisible(TestAction::make('revokeInvitation')->table($placeholder))
            ->assertActionHidden(TestAction::make('sendInvitation')->table($this->admin))
            ->callAction(TestAction::make('revokeInvitation')->table($placeholder))
            ->assertActionHidden(TestAction::make('revokeInvitation')->table($placeholder));

        $this->assertSame(InvitationStatus::NotInvited, $placeholder->fresh()->invitationStatus());
    }

    public function test_invitation_actions_are_available_on_the_user_view_page(): void
    {
        $placeholder = $this->inviteAdmin();
        $this->actAsAdmin();

        Livewire::test(ViewUser::class, ['record' => $placeholder->getRouteKey()])
            ->assertSee('Invitation')
            ->assertSee('new@example.com');
    }

    public function test_a_team_member_invitation_does_not_grant_site_flags(): void
    {
        $manager = User::factory()->create();
        $this->team->grant($manager, MembershipRole::Manager);

        $placeholder = app(SendTeamInvitationAction::class)->add($manager, $this->team, 'Sam Placeholder', 'new@example.com', MembershipRole::Member, Section::Adult);

        $this->assertFalse($placeholder->oversight);
        $this->assertFalse($placeholder->is_system_admin);
        $this->assertFalse($placeholder->invitation->isSiteInvitation());
    }

    public function test_create_user_and_setup_account_are_gone(): void
    {
        $oversight = User::factory()->create(['oversight' => true]);

        $this->assertFalse($this->admin->can('create', User::class));
        $this->assertFalse($oversight->can('create', User::class));
        $this->assertFalse(UserResource::canCreate());
        $this->assertArrayNotHasKey('create', UserResource::getPages());
        $this->assertFalse(Route::has('setup-account'));
    }

    public function test_registration_is_available_only_when_there_are_no_users(): void
    {
        User::query()->delete();

        $this->get(route('filament.member.auth.register'))->assertOk();

        User::factory()->create();

        $this->get(route('filament.member.auth.register'))->assertForbidden();
    }

    public function test_the_page_creates_an_account_from_a_site_invitation(): void
    {
        $placeholder = $this->inviteAdmin('new@example.com');

        Livewire::withQueryParams(['token' => $placeholder->invitation->token])
            ->test(AcceptInvitation::class, ['invitation' => $placeholder->invitation])
            ->set('password', 'secret-pass-1')
            ->set('password_confirmation', 'secret-pass-1')
            ->set('timezone', 'Europe/London')
            ->set('privacy_notice_accepted', true)
            ->call('createAccount')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($placeholder->fresh());
        $this->assertTrue($placeholder->fresh()->isSystemAdmin());
        $this->assertFalse($placeholder->fresh()->teams()->exists());
    }

    private function inviteAdmin(string $email = 'new@example.com', bool $oversight = false, bool $systemAdmin = true): User
    {
        return app(SendTeamInvitationAction::class)->addAdmin($this->admin, 'Sam Placeholder', $email, $oversight, $systemAdmin, Section::Adult);
    }

    private function acceptUrl(TeamInvitation $invitation): string
    {
        return URL::temporarySignedRoute('invitation.show', $invitation->expires_at, [
            'invitation' => $invitation,
            'token' => $invitation->token,
        ]);
    }

    private function actAsAdmin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($this->admin);
    }
}
