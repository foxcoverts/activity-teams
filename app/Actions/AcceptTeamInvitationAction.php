<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationDeclinedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptTeamInvitationAction
{
    /**
     * The placeholder becomes the new account, so nothing needs to move.
     *
     * @param  array{email: string, password: string, timezone: string, phone?: ?string, emergency_name?: ?string, emergency_phone?: ?string, name?: ?string}  $data
     */
    public function createAccount(TeamInvitation $invitation, array $data): User
    {
        $this->guardPending($invitation);

        $placeholder = $invitation->user;
        $email = Str::lower(trim($data['email']));
        $this->guardNewEmail($email);
        $subject = $this->activitySubject($invitation, $placeholder);

        DB::transaction(function () use ($invitation, $placeholder, $data, $email, $subject): void {
            $placeholder->forceFill([
                'name' => filled($data['name'] ?? null) ? $data['name'] : $placeholder->name,
                'email' => $email,
                'email_verified_at' => $email === Str::lower($invitation->email) ? now() : null,
                'password' => $data['password'],
                'timezone' => $data['timezone'],
                'phone' => $data['phone'] ?? null,
                'emergency_name' => $data['emergency_name'] ?? null,
                'emergency_phone' => $data['emergency_phone'] ?? null,
                'privacy_notice_accepted_at' => now(),
                'profile_confirmed_at' => now(),
            ])->save();

            $invitation->delete();

            activity()->performedOn($subject)->causedBy($placeholder)->log(__('Accepted an invitation and created an account'));
        });

        if (! $placeholder->hasVerifiedEmail()) {
            $placeholder->sendEmailVerificationNotification();
        }

        return $placeholder;
    }

    /**
     * Take over the placeholder's name in an existing account. The placeholder is removed afterwards.
     */
    public function claimWith(TeamInvitation $invitation, User $account): User
    {
        $this->guardPending($invitation);

        if ($account->isSuspended() || $account->isPlaceholder() || $account->is($invitation->user)) {
            throw ValidationException::withMessages([
                'account' => __('This account cannot accept the invitation.'),
            ]);
        }

        $placeholder = $invitation->user;
        $team = $invitation->team;
        $subject = $this->activitySubject($invitation, $account);

        DB::transaction(function () use ($placeholder, $account, $team, $subject): void {
            if ($team instanceof Team) {
                $role = $this->higherRole($team->memberships()->where('user_id', $placeholder->getKey())->value('role'), $team->memberships()->where('user_id', $account->getKey())->whereNull('left_at')->value('role'));

                $team->grant($account, $role);
            }

            $account->forceFill([
                'oversight' => $account->oversight || $placeholder->oversight,
                'is_system_admin' => $account->is_system_admin || $placeholder->is_system_admin,
            ])->save();

            $this->moveAttendance($placeholder, $account);

            DB::table('bookings')->where('lead_instructor_id', $placeholder->getKey())->update(['lead_instructor_id' => $account->getKey()]);
            DB::table('qualifications')->where('user_id', $placeholder->getKey())->update(['user_id' => $account->getKey()]);
            DB::table('keys')->where('holder_id', $placeholder->getKey())->update(['holder_id' => $account->getKey()]);

            $placeholder->forceDelete();

            activity()->performedOn($subject)->causedBy($account)->log(__('Accepted an invitation with an existing account'));
        });

        return $account;
    }

    public function decline(TeamInvitation $invitation): void
    {
        $this->guardPending($invitation);

        $invitation->forceFill(['declined_at' => now()])->save();

        activity()->performedOn($this->activitySubject($invitation, $invitation->user))->log(__('Declined an invitation'));

        Notification::send($this->declineRecipients($invitation), new TeamInvitationDeclinedNotification($invitation));
    }

    private function activitySubject(TeamInvitation $invitation, User $fallback): Model
    {
        return $invitation->team ?? $fallback;
    }

    /**
     * @return Collection<int, User>
     */
    private function declineRecipients(TeamInvitation $invitation)
    {
        $team = $invitation->team;

        if ($team instanceof Team) {
            return $team->managers()
                ->where('users.suspended', false)
                ->whereNotNull('users.password')
                ->whereNotNull('users.email_verified_at')
                ->get();
        }

        return User::query()
            ->where('is_system_admin', true)
            ->where('suspended', false)
            ->whereNotNull('password')
            ->whereNotNull('email_verified_at')
            ->get();
    }

    private function moveAttendance(User $placeholder, User $account): void
    {
        $existing = DB::table('booking_user')->where('user_id', $account->getKey())->get()->keyBy('booking_id');

        foreach (DB::table('booking_user')->where('user_id', $placeholder->getKey())->get() as $row) {
            $mine = $existing->get($row->booking_id);

            if ($mine === null) {
                DB::table('booking_user')
                    ->where('booking_id', $row->booking_id)
                    ->where('user_id', $placeholder->getKey())
                    ->update(['user_id' => $account->getKey()]);

                continue;
            }

            $needsAction = BookingAttendeeStatus::NeedsAction->value;

            if ($mine->status === $row->status || $row->status === $needsAction) {
                continue;
            }

            $update = $mine->status === $needsAction
                ? ['status' => $row->status, 'comment' => $row->comment]
                : ['status' => $needsAction];

            DB::table('booking_user')
                ->where('booking_id', $row->booking_id)
                ->where('user_id', $account->getKey())
                ->update($update);
        }
    }

    private function higherRole(mixed $placeholderRole, mixed $accountRole): MembershipRole
    {
        $roles = collect([$placeholderRole, $accountRole])
            ->filter()
            ->map(fn ($role): MembershipRole => $role instanceof MembershipRole ? $role : MembershipRole::from((string) $role));

        if ($roles->contains(MembershipRole::Manager)) {
            return MembershipRole::Manager;
        }

        if ($roles->contains(MembershipRole::Member)) {
            return MembershipRole::Member;
        }

        if ($roles->contains(MembershipRole::Parent)) {
            return MembershipRole::Parent;
        }

        return MembershipRole::Member;
    }

    private function guardPending(TeamInvitation $invitation): void
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation is no longer valid.'),
            ]);
        }
    }

    private function guardNewEmail(string $email): void
    {
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('This email address is already in use.'),
            ]);
        }
    }
}
