<?php

namespace Tests\Feature;

use App\Actions\AcceptTeamInvitationAction;
use App\Actions\DeleteUserAction;
use App\Actions\ExportPersonalDataAction;
use App\Actions\SendTeamInvitationAction;
use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Keys\Pages\CreateKey;
use App\Filament\Admin\Resources\Keys\Pages\EditKey;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Admin\Resources\Users\RelationManagers\MembershipsRelationManager;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Register;
use App\Filament\Manage\Resources\Members\Pages\ViewMember;
use App\Livewire\AcceptInvitation;
use App\Models\Key;
use App\Models\Qualification;
use App\Models\Team;
use App\Models\User;
use App\Notifications\KeyHolderLeavingNotification;
use App\Notifications\LastManagerLeavingNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AccountPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_download_a_json_copy_of_their_personal_data(): void
    {
        $user = User::factory()->teamMember()->underEighteen()->create([
            'email' => 'alex@example.com',
            'phone' => '+447700900111',
        ]);
        Qualification::factory()->create(['user_id' => $user->getKey()]);

        $payload = app(ExportPersonalDataAction::class)->export($user);

        $this->assertSame('alex@example.com', $payload['email']);
        $this->assertSame('+447700900111', $payload['phone']);
        $this->assertSame(true, $payload['under_18']);
        $this->assertArrayNotHasKey('section', $payload);
        $this->assertNotEmpty($payload['memberships']);
        $this->assertNotEmpty($payload['qualifications']);
        $this->assertArrayNotHasKey('password', $payload);
    }

    public function test_self_delete_with_the_password_soft_deletes_and_blocks_sign_in(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $user = User::factory()->teamMember()->create(['email' => 'leave@example.com']);

        app(DeleteUserAction::class)->delete($user, $user, 'password');

        $this->assertTrue($user->trashed());
        $this->assertNotNull(User::withTrashed()->findOrFail($user->getKey())->email);
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('member')));
        $this->assertFalse($admin->fresh()->trashed());
    }

    public function test_the_last_system_admin_cannot_delete_their_account(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        $this->expectException(ValidationException::class);

        app(DeleteUserAction::class)->delete($admin, $admin, 'password');
    }

    public function test_the_last_manager_of_a_team_can_delete_and_admins_are_notified(): void
    {
        Notification::fake();

        User::factory()->systemAdmin()->create(['email' => 'admin@example.com']);
        $manager = User::factory()->teamManager()->create(['name' => 'Last Manager']);

        app(DeleteUserAction::class)->delete($manager, $manager, 'password');

        $this->assertTrue($manager->trashed());
        Notification::assertSentTo(
            User::query()->where('email', 'admin@example.com')->first(),
            LastManagerLeavingNotification::class,
        );
    }

    public function test_a_deleted_person_is_read_only_except_restore(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        $team = Team::factory()->create(['name' => 'Climbing']);
        $manager = User::factory()->teamManager($team)->deleted()->create(['name' => 'Deleted Manager']);

        $manager = User::withTrashed()->findOrFail($manager->getKey());
        $membership = $manager->memberships()->firstOrFail();

        $this->assertTrue($admin->can('view', $manager));
        $this->assertTrue($admin->can('restore', $manager));
        $this->assertFalse($admin->can('update', $manager));
        $this->assertFalse($admin->can('delete', $manager));
        $this->assertFalse($admin->can('suspend', $manager));
        $this->assertFalse(Qualification::canMaintain($admin, $manager));

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $manager->getKey()])
            ->assertForbidden();

        Livewire::test(ViewUser::class, ['record' => $manager->getKey()])
            ->assertOk()
            ->assertActionHidden('edit')
            ->assertActionVisible('restore');

        Filament::setCurrentPanel(Filament::getPanel('manage'));
        Filament::setTenant($team);

        Livewire::test(ViewMember::class, ['record' => $manager->getKey()])
            ->assertOk()
            ->assertActionHidden('edit')
            ->assertActionHidden('changeRole')
            ->assertActionHidden('leave');

        Livewire::test(MembershipsRelationManager::class, [
            'ownerRecord' => $manager,
            'pageClass' => ViewUser::class,
        ])
            ->assertOk()
            ->assertSee('Climbing')
            ->assertActionHidden(TestAction::make('grant')->table())
            ->assertActionHidden(TestAction::make('changeRole')->table($membership))
            ->assertActionHidden(TestAction::make('leave')->table($membership));
    }

    public function test_a_deleted_person_cannot_be_added_to_a_team(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->deleted()->create();

        $this->expectException(ValidationException::class);

        $team->grant(User::withTrashed()->findOrFail($member->getKey()), MembershipRole::Member);
    }

    public function test_a_deleted_person_cannot_be_given_a_key_ring(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $member = User::factory()->deleted()->create(['name' => 'Deleted Holder']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(CreateKey::class)
            ->fillForm([
                'name' => 'Store keys',
                'holder_id' => $member->getKey(),
            ])
            ->call('create')
            ->assertHasFormErrors(['holder_id']);

        $this->assertDatabaseMissing('keys', ['name' => 'Store keys']);
    }

    public function test_a_key_ring_held_by_a_deleted_person_still_shows_their_name(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $holder = User::factory()->create(['name' => 'Deleted Holder']);
        $ring = Key::factory()->heldBy($holder)->create(['name' => 'Store keys']);
        $holder->delete();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(EditKey::class, ['record' => $ring->getKey()])
            ->assertOk()
            ->assertSee('Deleted Holder')
            ->fillForm([
                'name' => 'Store keys renamed',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $ring->refresh();

        $this->assertSame('Store keys renamed', $ring->name);
        $this->assertSame($holder->getKey(), $ring->holder_id);
    }

    public function test_key_rings_stay_assigned_and_managers_are_notified(): void
    {
        Notification::fake();

        $team = Team::factory()->create();
        $manager = User::factory()->teamManager($team)->create();
        $holder = User::factory()->teamMember($team)->create(['name' => 'Key Holder']);
        $ring = Key::factory()->heldBy($holder)->create(['name' => 'Store keys']);

        app(DeleteUserAction::class)->delete($holder, $holder, 'password');

        $this->assertTrue($holder->trashed());
        $this->assertSame($holder->getKey(), $ring->fresh()->holder_id);
        Notification::assertSentTo($manager, KeyHolderLeavingNotification::class);
    }

    public function test_purge_after_thirty_days_clears_contact_and_keeps_the_name_and_qualifications(): void
    {
        $user = User::factory()->create(['name' => 'Kept Name', 'email' => 'gone@example.com']);
        $qualification = Qualification::factory()->create(['user_id' => $user->getKey()]);

        app(DeleteUserAction::class)->delete($user, $user, 'password');

        $this->travel(31)->days();
        $this->artisan('users:purge-deleted')->assertSuccessful();

        $user = User::withTrashed()->findOrFail($user->getKey());

        $this->assertNull($user->email);
        $this->assertNull($user->password);
        $this->assertSame('Kept Name', $user->name);
        $this->assertTrue($qualification->fresh()->exists());
        $this->assertFalse($user->isPlaceholder());
    }

    public function test_a_deleted_placeholder_is_not_treated_as_a_live_placeholder(): void
    {
        $placeholder = User::factory()->placeholder()->deleted()->create();
        $oversight = User::factory()->oversight()->create();

        $this->assertTrue($placeholder->trashed());
        $this->assertFalse($placeholder->isPlaceholder());

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($oversight);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$placeholder])
            ->assertSee('Deleted');
    }

    public function test_claiming_an_invitation_still_removes_the_placeholder_entirely(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $placeholder = app(SendTeamInvitationAction::class)->add($manager, $team, 'Sam', 'new@example.com', MembershipRole::Member);
        $account = User::factory()->create();

        app(AcceptTeamInvitationAction::class)->claimWith($placeholder->invitation, $account);

        $this->assertNull(User::withTrashed()->find($placeholder->getKey()));
    }

    public function test_creating_an_account_without_privacy_acknowledgement_is_refused(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $placeholder = app(SendTeamInvitationAction::class)->add($manager, $team, 'Sam', 'new@example.com', MembershipRole::Member);

        Livewire::withQueryParams(['token' => $placeholder->invitation->token])
            ->test(AcceptInvitation::class, ['invitation' => $placeholder->invitation])
            ->set('password', 'secret-pass-1')
            ->set('password_confirmation', 'secret-pass-1')
            ->set('timezone', 'Europe/London')
            ->call('createAccount')
            ->assertHasErrors(['privacy_notice_accepted']);
    }

    public function test_first_user_register_requires_privacy_acknowledgement(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('member'));

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'First Admin',
                'email' => 'first@example.com',
                'password' => 'secret-pass-1',
                'passwordConfirmation' => 'secret-pass-1',
                'timezone' => 'Europe/London',
                'privacy_notice_accepted' => false,
            ])
            ->call('register')
            ->assertHasFormErrors(['privacy_notice_accepted']);
    }

    public function test_self_delete_from_the_profile_signs_the_person_out(): void
    {
        $user = User::factory()->teamMember()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->callAction('deleteAccount', ['password' => 'password'])
            ->assertRedirect(route('account.deleted'));

        $this->assertTrue(User::withTrashed()->findOrFail($user->getKey())->trashed());
    }
}
