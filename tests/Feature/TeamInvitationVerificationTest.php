<?php

namespace Tests\Feature;

use App\Actions\AcceptTeamInvitationAction;
use App\Actions\SendTeamInvitationAction;
use App\Enums\MembershipRole;
use App\Models\Team;
use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamInvitationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_changed_email_signup_sends_a_renderable_filament_verification_notification(): void
    {
        $this->freezeTime();

        $team = Team::factory()->create();
        $manager = User::factory()->create();
        $team->grant($manager, MembershipRole::Manager);
        $placeholder = app(SendTeamInvitationAction::class)->add(
            $manager,
            $team,
            'Sam Placeholder',
            'invited@example.com',
            MembershipRole::Member,
        );

        $user = app(AcceptTeamInvitationAction::class)->createAccount($placeholder->invitation, [
            'email' => 'new-address@example.com',
            'password' => 'secret-pass-1',
            'timezone' => 'Europe/London',
        ]);

        $notification = new VerifyEmail;
        $notification->url = Filament::getPanel('member')->getVerifyEmailUrl($user);
        $mail = $notification->toMail($user);

        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame($notification->url, $mail->actionUrl);
    }
}
