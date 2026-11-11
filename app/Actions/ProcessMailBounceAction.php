<?php

namespace App\Actions;

use App\Enums\OutboundMailStatus;
use App\Models\InboxNotification;
use App\Models\MailBounce;
use App\Models\OutboundMail;
use App\Models\User;
use App\Notifications\EmailBouncedNotification;
use App\Support\BounceReport;
use Illuminate\Support\Facades\Notification;

class ProcessMailBounceAction
{
    public function handle(BounceReport $report): ?MailBounce
    {
        if (filled($report->providerMessageId)) {
            $existing = MailBounce::query()
                ->where('provider', $report->provider)
                ->where('provider_message_id', $report->providerMessageId)
                ->first();

            if ($existing instanceof MailBounce) {
                return $existing;
            }
        }

        $outbound = $this->resolveOutboundMail($report);

        if (! $outbound instanceof OutboundMail) {
            return null;
        }

        $bounce = $outbound->bounces()->create([
            'provider' => $report->provider,
            'provider_message_id' => $report->providerMessageId,
            'category' => $report->category,
            'action' => $report->action,
            'message' => $report->message,
            'hard' => $report->isHard(),
            'bounced_at' => $report->bouncedAt,
        ]);

        $outbound->forceFill([
            'status' => OutboundMailStatus::Bounced,
        ])->save();

        $this->releaseInbox($outbound);

        if ($report->isHard()) {
            $this->flagUser($outbound, $report);
        }

        return $bounce;
    }

    private function resolveOutboundMail(BounceReport $report): ?OutboundMail
    {
        if (filled($report->messageId)) {
            $byMessageId = OutboundMail::query()
                ->where('message_id', $report->messageId)
                ->where('recipient', $report->recipient)
                ->first()
                ?? OutboundMail::query()
                    ->where('message_id', $report->messageId)
                    ->first();

            if ($byMessageId instanceof OutboundMail) {
                return $byMessageId;
            }
        }

        return OutboundMail::query()
            ->where('recipient', $report->recipient)
            ->where('status', OutboundMailStatus::Sent)
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('created_at')
            ->first();
    }

    private function releaseInbox(OutboundMail $outbound): void
    {
        InboxNotification::query()
            ->where('outbound_message_id', $outbound->message_id)
            ->each(fn (InboxNotification $row) => $row->releaseAfterBounce());
    }

    private function flagUser(OutboundMail $outbound, BounceReport $report): void
    {
        $user = $outbound->user
            ?? User::query()->where('email', $report->recipient)->first();

        if (! $user instanceof User) {
            return;
        }

        $alreadyFlagged = $user->email_bounced_at !== null;

        $user->forceFill([
            'email_bounced_at' => $report->bouncedAt,
            'email_bounce_category' => $report->category,
        ])->save();

        if ($alreadyFlagged) {
            return;
        }

        $this->notifyManagers($user);
    }

    private function notifyManagers(User $member): void
    {
        $member->loadMissing(['teams.managers']);

        $managers = $member->teams
            ->flatMap(fn ($team) => $team->managers)
            ->unique(fn (User $manager): string => (string) $manager->getKey())
            ->filter(fn (User $manager): bool => $manager->isActive()
                && ! $manager->isSuspended()
                && $manager->hasVerifiedEmail()
                && ! $manager->is($member))
            ->values();

        if ($managers->isEmpty()) {
            return;
        }

        Notification::send($managers, new EmailBouncedNotification($member));
    }
}
