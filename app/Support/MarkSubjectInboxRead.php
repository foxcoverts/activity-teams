<?php

namespace App\Support;

use App\Models\InboxNotification;
use App\Models\User;
use App\Notifications\AttendanceRecordedNotification;
use App\Notifications\BookingHeldNotification;
use App\Notifications\CommentNotification;
use App\Notifications\Contracts\ClearsWhenSubjectViewed;
use App\Notifications\MerchandiseOrderApprovedNotification;
use App\Notifications\MerchandiseOrderRejectedNotification;
use App\Notifications\NewsPublishedNotification;
use App\Notifications\TaskHeldNotification;
use Illuminate\Database\Eloquent\Model;

final class MarkSubjectInboxRead
{
    /**
     * Opt-in attention notices cleared when their subject is viewed.
     * Keep in sync with classes that implement ClearsWhenSubjectViewed.
     *
     * @var list<class-string<ClearsWhenSubjectViewed>>
     */
    private const TYPES = [
        AttendanceRecordedNotification::class,
        BookingHeldNotification::class,
        CommentNotification::class,
        MerchandiseOrderApprovedNotification::class,
        MerchandiseOrderRejectedNotification::class,
        NewsPublishedNotification::class,
        TaskHeldNotification::class,
    ];

    public function __invoke(?User $user, Model $subject): void
    {
        if (! $user instanceof User) {
            return;
        }

        $user->unreadNotifications()
            ->whereIn('type', self::TYPES)
            ->where('data->subject_type', $subject::class)
            ->where('data->subject_id', $subject->getKey())
            ->orderBy('id')
            ->get()
            ->each(fn (InboxNotification $notification) => $notification->markReadForDelivery());
    }
}
