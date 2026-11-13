<?php

namespace App\Support;

use App\Enums\InvitationNudgeTier;
use App\Enums\TentativeTriage;
use App\Filament\Member\Pages\Dashboard;
use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Filament\Teams\Pages\ManagerDashboard;
use App\Mail\BookingInvitationNudgeMail;
use App\Mail\BookingSessionReminderMail;
use App\Mail\CombinedDigestMail;
use App\Mail\ManagerAttentionNudgeMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\InboxNotification;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use App\Notifications\LeadNeededNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class CombinedDigest
{
    public function __construct(
        private RotaDigest $rota,
        private InboundReviewNudge $inbound,
        private BookingCatchUp $catchUp,
        private ManagerAttentionCatchUp $managerAttention,
        private DebriefCatchUp $debriefs,
    ) {}

    public function sendAll(): void
    {
        $this->inbound->record();

        $ids = InboxNotification::query()->outstanding()->pluck('notifiable_id')
            ->merge($this->rota->candidateUserIds())
            ->merge($this->catchUp->candidateUserIds())
            ->merge($this->managerAttention->candidateUserIds())
            ->merge($this->debriefs->candidateUserIds())
            ->unique()
            ->filter()
            ->values();

        User::query()->whereKey($ids->all())->each(function (User $user): void {
            $this->send($user);
        });
    }

    public function send(User $user): void
    {
        $rota = $this->rota->delta($user);
        $outstanding = $user->notifications()->outstanding()->get();
        $emailable = $outstanding->filter(fn (InboxNotification $row): bool => $this->emailable($user, $row));
        $count = $emailable->count();
        $rotaChanged = $rota['changed'];
        $nudges = $this->catchUp->dueInvitationNudges($user);
        $reminders = $this->catchUp->dueSessionReminders($user);
        $catchUpCount = $nudges->count() + $reminders->count();
        $attention = $this->managerAttention->due($user);
        $attentionNew = $attention['new'];
        $attentionOutstanding = $attentionNew->isEmpty()
            ? collect()
            : $attention['outstanding'];
        $attentionNewCount = $attentionNew->count();
        $debriefFocused = $this->debriefs->dueFocusedNudges($user);
        $debriefFocusedCount = $debriefFocused->count();
        $debriefSummary = $this->debriefs->outstandingSummary($user);

        if (
            $count === 0
            && ! $rotaChanged
            && $catchUpCount === 0
            && $attentionNewCount === 0
            && $debriefFocusedCount === 0
        ) {
            return;
        }

        if (
            $count === 0
            && ! $rotaChanged
            && $catchUpCount === 0
            && $attentionNewCount === 0
            && $debriefFocusedCount === 1
        ) {
            $item = $debriefFocused->first();
            $this->debriefs->sendFocused($user, $item['booking'], $item['week']);

            return;
        }

        if ($count === 0 && ! $rotaChanged && $catchUpCount === 1 && $attentionNewCount === 0 && $debriefFocusedCount === 0) {
            $this->sendFocusedCatchUp($user, $nudges, $reminders);

            return;
        }

        if ($count === 0 && ! $rotaChanged && $catchUpCount === 0 && $attentionNewCount === 1 && $attentionOutstanding->isEmpty() && $debriefFocusedCount === 0) {
            $this->sendFocusedAttention($user, $attentionNew);

            return;
        }

        if ($count === 1 && ! $rotaChanged && $catchUpCount === 0 && $attentionNewCount === 0 && $debriefFocusedCount === 0) {
            $row = $emailable->first();

            if ($row instanceof InboxNotification) {
                $this->sendExisting($user, $row);
            }

            return;
        }

        $unreadRemainder = max(0, $user->unreadNotifications()->count() - $outstanding->count());
        $includeDebriefSummary = $debriefSummary->isNotEmpty()
            && ($count > 0 || $rotaChanged || $catchUpCount > 0 || $attentionNewCount > 0 || $debriefFocusedCount > 0);

        Mail::to($user)->send(new CombinedDigestMail(
            outstanding: $count === 0 ? null : $outstanding->count(),
            unreadRemainder: $count === 0 ? 0 : $unreadRemainder,
            rota: $rotaChanged ? $rota : null,
            invitationNudges: $nudges->isEmpty() ? null : $nudges->pluck('label')->all(),
            sessionReminders: $reminders->isEmpty() ? null : $reminders->pluck('label')->all(),
            attentionNew: $attentionNew->isEmpty() ? null : $attentionNew->pluck('label')->all(),
            attentionOutstanding: $attentionOutstanding->isEmpty() ? null : $attentionOutstanding->pluck('label')->all(),
            debriefsDue: $includeDebriefSummary ? $debriefSummary->pluck('label')->all() : null,
            inboxUrl: InboxResource::getUrl(panel: 'member'),
            dashboardUrl: Dashboard::getUrl(panel: 'member'),
            managerUrl: $this->managerUrl($attentionNew->merge($attentionOutstanding)),
        ));

        if ($count > 0) {
            $outstanding->each(function (InboxNotification $row): void {
                $row->forceFill(['digest_notified_at' => now()])->save();
            });
        }

        $this->stampCatchUp($nudges, $reminders);
        $this->managerAttention->stamp($user, $attentionNew->merge($attentionOutstanding));

        foreach ($debriefFocused as $item) {
            $this->debriefs->mark($item['booking'], $item['week']);
        }
    }

    /**
     * @param  Collection<int, array{attendance: BookingAttendance, booking: Booking, tier: InvitationNudgeTier, label: string}>  $nudges
     * @param  Collection<int, array{attendance: BookingAttendance, booking: Booking, label: string}>  $reminders
     */
    private function sendFocusedCatchUp(User $user, Collection $nudges, Collection $reminders): void
    {
        if ($nudges->count() === 1) {
            $item = $nudges->first();
            Mail::to($user)->send(new BookingInvitationNudgeMail($item['booking'], $user));
            $this->catchUp->markInvitationNudge($item['attendance'], $item['tier']);

            return;
        }

        $item = $reminders->first();
        Mail::to($user)->send(new BookingSessionReminderMail($item['booking'], $user));
        $this->catchUp->markSessionReminder($item['attendance']);
    }

    /**
     * @param  Collection<int, array{booking: Booking, triage: TentativeTriage, label: string, team: Team}>  $items
     */
    private function sendFocusedAttention(User $user, Collection $items): void
    {
        $item = $items->first();
        Mail::to($user)->send(new ManagerAttentionNudgeMail($item['booking'], $item['triage'], $item['team']));
        $this->managerAttention->stamp($user, $items);
    }

    /**
     * @param  Collection<int, array{attendance: BookingAttendance, booking: Booking, tier: InvitationNudgeTier, label: string}>  $nudges
     * @param  Collection<int, array{attendance: BookingAttendance, booking: Booking, label: string}>  $reminders
     */
    private function stampCatchUp(Collection $nudges, Collection $reminders): void
    {
        foreach ($nudges as $item) {
            $this->catchUp->markInvitationNudge($item['attendance'], $item['tier']);
        }

        foreach ($reminders as $item) {
            $this->catchUp->markSessionReminder($item['attendance']);
        }
    }

    /**
     * @param  Collection<int, array{booking: Booking, triage: TentativeTriage, label: string, team: Team}>  $items
     */
    private function managerUrl(Collection $items): ?string
    {
        if ($items->isEmpty()) {
            return null;
        }

        return ManagerDashboard::getUrl(panel: 'teams', tenant: $items->first()['team']);
    }

    private function emailable(User $user, InboxNotification $row): bool
    {
        if ($row->type === LeadNeededNotification::class) {
            return false;
        }

        if ($row->isInAppOnly()) {
            return false;
        }

        if ($row->isAlwaysEmailable()) {
            return true;
        }

        $channel = $row->mailChannel();

        if ($channel === null) {
            return false;
        }

        return MailPreference::allows($user, $channel, $row->preferenceSubject());
    }

    private function sendExisting(User $user, InboxNotification $row): void
    {
        $type = $row->type;

        if (! is_string($type) || ! class_exists($type) || ! method_exists($type, 'fromInbox')) {
            return;
        }

        $notification = $type::fromInbox($row->data);

        if (! $notification instanceof Notification || ! in_array(RecordsInbox::class, class_uses_recursive($notification), true)) {
            return;
        }

        $message = $notification->toMail($user);

        if ($message instanceof Mailable) {
            Mail::to($user)->send($message);
        } else {
            app(MailChannel::class)->send($user, $notification);
        }

        $row->forceFill([
            'read_at' => now(),
            'digest_notified_at' => now(),
            'outbound_message_id' => (string) Str::uuid(),
        ])->save();
    }
}
