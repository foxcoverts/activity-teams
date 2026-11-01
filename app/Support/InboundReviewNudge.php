<?php

namespace App\Support;

use App\Enums\InboundMailOutcome;
use App\Models\InboundMail;
use App\Models\InboxNotification;
use App\Models\User;
use App\Notifications\InboundReviewNotification;
use Illuminate\Database\Eloquent\Collection;

final class InboundReviewNudge
{
    public function record(): void
    {
        $unreviewed = InboundMail::query()
            ->whereIn('outcome', [InboundMailOutcome::Queued, InboundMailOutcome::Failed]);

        $count = (clone $unreviewed)->count();

        if ($count === 0) {
            return;
        }

        $newest = (clone $unreviewed)->max('id');

        foreach ($this->admins() as $admin) {
            $outstanding = $admin->notifications()
                ->where('type', InboundReviewNotification::class)
                ->outstanding()
                ->first();

            if ($outstanding instanceof InboxNotification) {
                $this->refresh($outstanding, $count, (string) $newest);

                continue;
            }

            $latest = $admin->notifications()
                ->where('type', InboundReviewNotification::class)
                ->latest()
                ->first();

            $watermark = $latest instanceof InboxNotification
                ? ($latest->data['rebuild']['watermark'] ?? null)
                : null;

            if (is_string($watermark) && strcmp((string) $newest, $watermark) <= 0) {
                continue;
            }

            $admin->notify(new InboundReviewNotification($count, (string) $newest));
        }
    }

    private function refresh(InboxNotification $row, int $count, string $watermark): void
    {
        $data = $row->data;
        $data['body'] = InboundReviewNotification::sentence($count);
        $data['rebuild'] = [
            'count' => $count,
            'watermark' => $watermark,
        ];
        $row->forceFill(['data' => $data])->save();
    }

    /**
     * @return Collection<int, User>
     */
    private function admins()
    {
        return User::query()
            ->where('is_system_admin', true)
            ->where('suspended', false)
            ->whereNotNull('password')
            ->whereNotNull('email_verified_at')
            ->get();
    }
}
