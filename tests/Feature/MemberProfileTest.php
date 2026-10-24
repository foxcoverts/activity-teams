<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Filament\Auth\EditProfile;
use App\Filament\Manage\Resources\Users\Pages\EditUser;
use App\Filament\Pages\ChangePassword;
use App\Models\Team;
use App\Models\User;
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

        Filament::setCurrentPanel(Filament::getPanel('member'));
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

        Filament::setCurrentPanel(Filament::getPanel('member'));
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

    public function test_a_team_less_system_admin_can_open_the_profile(): void
    {
        $admin = User::factory()->create(['is_system_admin' => true]);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($admin);

        Livewire::test(EditProfile::class)
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee('Personal details')
            ->assertSee('Name')
            ->assertSee('Email address')
            ->assertSee('Privacy notice')
            ->assertDontSee('filament-panels::');
    }

    public function test_the_manage_user_menu_links_profile_and_change_password_to_the_member_panel(): void
    {
        $manager = User::factory()->teamLeader()->create();
        $managePanel = Filament::getPanel('manage');
        $team = $manager->getDefaultTenant($managePanel);
        $profileUrl = EditProfile::getUrl(panel: 'member');
        $changePasswordUrl = ChangePassword::getUrl(panel: 'member');

        $this->actingAs($manager);

        $this->get($managePanel->getUrl($team))
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

        Filament::setCurrentPanel(Filament::getPanel('member'));
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

        Filament::setCurrentPanel(Filament::getPanel('member'));
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
        $team = Team::factory()->create();
        $admin = User::factory()->create(['is_system_admin' => true]);
        $member = User::factory()->create(['name' => 'Alex Member']);
        $team->grant($member, MembershipRole::Member);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        Filament::setTenant($team);

        Livewire::test(EditUser::class, ['record' => $member->getKey()])
            ->assertSee('Only the user can change their own password.');
    }
}
