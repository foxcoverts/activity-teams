<?php

namespace App\Support;

use App\Enums\InvitationNudgeTier;
use App\Filament\Member\Pages\Dashboard;
use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Mail\BookingInvitationNudgeMail;
use App\Mail\BookingSessionReminderMail;
use App\Mail\CombinedDigestMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\InboxNotification;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
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
    ) {}

    public function sendAll(): void
    {
        $this->inbound->record();

        $ids = InboxNotification::query()->outstanding()->pluck('notifiable_id')
            ->merge($this->rota->candidateUserIds())
            ->merge($this->catchUp->candidateUserIds())
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

        if ($count === 0 && ! $rotaChanged && $catchUpCount === 0) {
            return;
        }

        if ($count === 0 && ! $rotaChanged && $catchUpCount === 1) {
            $this->sendFocusedCatchUp($user, $nudges, $reminders);

            return;
        }

        if ($count === 1 && ! $rotaChanged && $catchUpCount === 0) {
            $row = $emailable->first();

            if ($row instanceof InboxNotification) {
                $this->sendExisting($user, $row);
            }

            return;
        }

        $unreadRemainder = max(0, $user->unreadNotifications()->count() - $outstanding->count());

        Mail::to($user)->send(new CombinedDigestMail(
            outstanding: $count === 0 ? null : $outstanding->count(),
            unreadRemainder: $count === 0 ? 0 : $unreadRemainder,
            rota: $rotaChanged ? $rota : null,
            invitationNudges: $nudges->isEmpty() ? null : $nudges->pluck('label')->all(),
            sessionReminders: $reminders->isEmpty() ? null : $reminders->pluck('label')->all(),
            inboxUrl: InboxResource::getUrl(panel: 'member'),
            dashboardUrl: Dashboard::getUrl(panel: 'member'),
        ));

        if ($count > 0) {
            $outstanding->each(function (InboxNotification $row): void {
                $row->forceFill(['digest_notified_at' => now()])->save();
            });
        }

        $this->stampCatchUp($nudges, $reminders);
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

    private function emailable(User $user, InboxNotification $row): bool
    {
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
