<?php

namespace App\Notifications;

use App\Enums\MailChannel;
use App\Mail\BookingCommentMail;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class BookingCommentNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Comment $comment)
    {
        $this->afterCommit();
        $this->comment->loadMissing(['author', 'commentable']);
    }

    public function inboxBody(object $notifiable): string
    {
        $booking = $this->booking();
        $activity = $booking?->activityTitle() ?? __('a booking');

        return __(':author commented on :activity.', [
            'author' => $this->comment->author?->name ?? __('someone'),
            'activity' => $activity,
        ]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        return $this->comment->url();
    }

    public function inboxChannel(): ?MailChannel
    {
        return MailChannel::BookingsComments;
    }

    public function inboxSubject(): ?Booking
    {
        return $this->booking();
    }

    /**
     * @return array<string, mixed>
     */
    public function inboxRebuild(): array
    {
        return [
            'comment_id' => $this->comment->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        $rebuild = $data['rebuild'];

        return new self(Comment::query()->findOrFail($rebuild['comment_id']));
    }

    public function toMail(object $notifiable): Mailable
    {
        assert($notifiable instanceof User);

        return new BookingCommentMail($this->comment, $notifiable);
    }

    private function booking(): ?Booking
    {
        $commentable = $this->comment->commentable;

        return $commentable instanceof Booking ? $commentable : null;
    }
}
