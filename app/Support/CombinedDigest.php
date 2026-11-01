<?php

namespace App\Support;

use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Mail\CombinedDigestMail;
use App\Models\InboxNotification;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class CombinedDigest
{
    public function __construct(
        private RotaDigest $rota,
        private InboundReviewNudge $inbound,
    ) {}

    public function sendAll(): void
    {
        $this->inbound->record();

        $ids = InboxNotification::query()->outstanding()->pluck('notifiable_id')
            ->merge($this->rota->candidateUserIds())
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

        if ($count === 0 && ! $rotaChanged) {
            return;
        }

        if ($count === 1 && ! $rotaChanged) {
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
            inboxUrl: InboxResource::getUrl(panel: 'member'),
        ));

        if ($count > 0) {
            $outstanding->each(function (InboxNotification $row): void {
                $row->forceFill(['digest_notified_at' => now()])->save();
            });
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
