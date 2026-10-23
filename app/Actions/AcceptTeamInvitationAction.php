<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationDeclinedNotification;
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

        DB::transaction(function () use ($invitation, $placeholder, $data, $email): void {
            $placeholder->forceFill([
                'name' => filled($data['name'] ?? null) ? $data['name'] : $placeholder->name,
                'email' => $email,
                'email_verified_at' => $email === Str::lower($invitation->email) ? now() : null,
                'password' => $data['password'],
                'timezone' => $data['timezone'],
                'phone' => $data['phone'] ?? null,
                'emergency_name' => $data['emergency_name'] ?? null,
                'emergency_phone' => $data['emergency_phone'] ?? null,
            ])->save();

            $invitation->delete();

            activity()->performedOn($invitation->team)->causedBy($placeholder)->log(__('Accepted an invitation and created an account'));
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

        DB::transaction(function () use ($placeholder, $account, $team): void {
            $role = $this->higherRole($team->memberships()->where('user_id', $placeholder->getKey())->value('role'), $team->memberships()->where('user_id', $account->getKey())->whereNull('left_at')->value('role'));

            $team->grant($account, $role);

            $this->moveAttendance($placeholder, $account);

            DB::table('bookings')->where('lead_instructor_id', $placeholder->getKey())->update(['lead_instructor_id' => $account->getKey()]);
            DB::table('qualifications')->where('user_id', $placeholder->getKey())->update(['user_id' => $account->getKey()]);
            DB::table('key_rings')->where('holder_id', $placeholder->getKey())->update(['holder_id' => $account->getKey()]);

            $placeholder->delete();

            activity()->performedOn($team)->causedBy($account)->log(__('Accepted an invitation with an existing account'));
        });

        return $account;
    }

    public function decline(TeamInvitation $invitation): void
    {
        $this->guardPending($invitation);

        $invitation->forceFill(['declined_at' => now()])->save();

        activity()->performedOn($invitation->team)->log(__('Declined an invitation'));

        $managers = $invitation->team->managers()
            ->where('users.suspended', false)
            ->whereNotNull('users.password')
            ->whereNotNull('users.email_verified_at')
            ->get();

        Notification::send($managers, new TeamInvitationDeclinedNotification($invitation));
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

        return $roles->contains(MembershipRole::Manager) ? MembershipRole::Manager : MembershipRole::Member;
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
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('This email address is already in use.'),
            ]);
        }
    }
}
