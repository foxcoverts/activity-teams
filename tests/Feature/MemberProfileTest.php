<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Auth\EditProfile;
use App\Filament\Pages\ChangePassword;
use App\Models\User;
use App\Support\Avatars;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_update_their_profile_contact_details(): void
    {
        $user = User::factory()->teamMember()->create([
            'email' => 'old@example.com',
            'phone' => '+447700900000',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'Alex Member',
                'email' => 'old@example.com',
                'phone' => '+447700900111',
                'emergency_name' => 'Pat',
                'emergency_phone' => '+447700900222',
                'timezone' => 'Europe/Paris',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Alex Member', $user->name);
        $this->assertSame('+447700900111', $user->phone);
        $this->assertSame('Pat', $user->emergency_name);
        $this->assertSame('Europe/Paris', $user->timezoneName());
        $this->assertNotNull($user->profile_confirmed_at);
    }

    public function test_changing_email_on_the_profile_clears_verification(): void
    {
        $user = User::factory()->teamMember()->create(['email' => 'old@example.com']);

        Filament::setCurrentPanel('member');
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => $user->name,
                'email' => 'new@example.com',
                'timezone' => 'Europe/London',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_the_profile_explains_how_to_change_the_gravatar_photo(): void
    {
        $user = User::factory()->teamMember()->create([
            'email' => 'Ada@Example.COM',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->assertSeeTextInOrder(['Personal details', 'Contact details', 'Emergency contact', 'Avatar'])
            ->assertSee('Your photo comes from Gravatar, using the email on this account.')
            ->assertSee('soft initials generated from your name')
            ->assertSee('Changing your email changes which Gravatar is used.')
            ->assertSee('Change on Gravatar')
            ->assertSee('fi-user-avatar', false)
            ->assertSee(
                str_replace('&', '&amp;', Avatars::userUrl($user)),
                false,
            )
            ->assertSee('https://gravatar.com/profile/avatars', false);
    }

    public function test_a_team_less_system_admin_can_open_the_profile(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        Filament::setCurrentPanel('member');
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee('Personal details')
            ->assertSee('Name')
            ->assertSee('Email address')
            ->assertSee('Privacy notice')
            ->assertSee(route('privacy'), escape: false)
            ->assertDontSee('filament-panels::');
    }

    public function test_the_teams_user_menu_links_profile_and_change_password_to_the_member_panel(): void
    {
        $manager = User::factory()->teamManager()->create();
        $teamsPanel = Filament::getPanel('teams');
        $team = $manager->getDefaultTenant($teamsPanel);
        $profileUrl = EditProfile::getUrl(panel: 'member');
        $changePasswordUrl = ChangePassword::getUrl(panel: 'member');

        $this->actingAs($manager);

        $this->get($teamsPanel->getUrl($team))
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee($profileUrl, escape: false)
            ->assertSee($changePasswordUrl, escape: false)
            ->assertDontSee('filament-panels::');

        $this->get($profileUrl)
            ->assertOk()
            ->assertSee('Personal details')
            ->assertSee('Name')
            ->assertSee('Email address')
            ->assertSee('Save changes')
            ->assertDontSee('filament-panels::');

        $this->get($changePasswordUrl)->assertOk();
    }

    public function test_change_password_is_in_the_user_menu_not_the_sidebar(): void
    {
        $user = User::factory()->teamMember()->create();

        Filament::setCurrentPanel('member');
        $this->actingAs($user);

        $this->assertFalse(ChangePassword::shouldRegisterNavigation());

        $menuItem = Filament::getCurrentOrDefaultPanel()->getUserMenuItems()['changePassword'] ?? null;

        $this->assertNotNull($menuItem);
        $this->assertSame(__('Change password'), $menuItem->getLabel());
        $this->assertSame(ChangePassword::getUrl(panel: 'member'), $menuItem->getUrl());
    }

    public function test_a_member_can_change_their_password_on_the_separate_page(): void
    {
        $user = User::factory()->teamMember()->create();

        Filament::setCurrentPanel('member');
        $this->actingAs($user);

        Livewire::test(ChangePassword::class)
            ->assertSee('Confirm new password')
            ->fillForm([
                'currentPassword' => 'password',
                'password' => 'new-secret-pass-1',
                'passwordConfirmation' => 'new-secret-pass-1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('new-secret-pass-1', $user->fresh()->password));
    }

    public function test_managers_cannot_set_another_persons_password(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $member = User::factory()->teamMember()->create(['name' => 'Alex Member']);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $member->getKey()])
            ->assertSee('Only the user can change their own password.');
    }
}
