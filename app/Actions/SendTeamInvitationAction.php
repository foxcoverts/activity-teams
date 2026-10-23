<?php

namespace App\Actions;

use App\Enums\MembershipRole;
use App\Enums\Section;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SendTeamInvitationAction
{
    /**
     * Add a named placeholder to the team and invite them by email.
     */
    public function add(User $manager, Team $team, string $name, string $email, MembershipRole $role, Section $section): User
    {
        $this->authorize($manager, $team);
        $email = $this->normalise($email);
        $this->guardEmail($team, $email);

        $placeholder = DB::transaction(function () use ($team, $name, $role, $section): User {
            $placeholder = User::query()->create([
                'name' => $name,
                'section' => $section,
            ]);

            $team->grant($placeholder, $role);

            return $placeholder;
        });

        $this->issue($manager, $team, $placeholder, $email);

        return $placeholder;
    }

    /**
     * Send, resend or replace the invitation for an existing placeholder. Any earlier link stops working.
     */
    public function send(User $manager, Team $team, User $placeholder, string $email): TeamInvitation
    {
        $this->authorize($manager, $team);

        if (! $placeholder->isPlaceholder() || ! $placeholder->teams()->whereKey($team->getKey())->exists()) {
            throw ValidationException::withMessages([
                'email' => __('Only people who have not yet created an account can be invited.'),
            ]);
        }

        $email = $this->normalise($email);
        $this->guardEmail($team, $email);

        return $this->issue($manager, $team, $placeholder, $email);
    }

    public function revoke(User $manager, Team $team, User $placeholder): void
    {
        $this->authorize($manager, $team);

        $invitation = $placeholder->invitation;

        if ($invitation instanceof TeamInvitation && $invitation->isPending()) {
            $invitation->forceFill(['revoked_at' => now()])->save();

            activity()->performedOn($team)->causedBy($manager)->log(__('Revoked the invitation for :name', ['name' => $placeholder->name]));
        }
    }

    private function issue(User $manager, Team $team, User $placeholder, string $email): TeamInvitation
    {
        $invitation = TeamInvitation::query()->updateOrCreate(
            ['user_id' => $placeholder->getKey()],
            [
                'team_id' => $team->getKey(),
                'email' => $email,
                'token' => Str::random(40),
                'sent_at' => now(),
                'expires_at' => now()->addDays(TeamInvitation::VALID_DAYS),
                'declined_at' => null,
                'revoked_at' => null,
            ],
        );

        activity()->performedOn($team)->causedBy($manager)->log(__('Invited :name', ['name' => $placeholder->name]));

        Notification::route('mail', [$email => $placeholder->name])->notify(new TeamInvitationNotification($invitation));

        return $invitation;
    }

    private function guardEmail(Team $team, string $email): void
    {
        $existing = User::query()->where('email', $email)->first();

        if (! $existing instanceof User) {
            return;
        }

        if ($existing->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => __('This email address belongs to a suspended account, so it cannot be invited.'),
            ]);
        }

        if ($existing->teams()->whereKey($team->getKey())->exists()) {
            throw ValidationException::withMessages([
                'email' => __('This email address already belongs to a member of this team.'),
            ]);
        }
    }

    private function authorize(User $manager, Team $team): void
    {
        if (! $manager->canInviteTo($team)) {
            throw new AuthorizationException;
        }
    }

    private function normalise(string $email): string
    {
        return Str::lower(trim($email));
    }
}
