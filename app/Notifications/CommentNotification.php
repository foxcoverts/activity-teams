<?php

namespace App\Notifications;

use App\Enums\MailChannel;
use App\Mail\CommentMail;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class CommentNotification extends Notification
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
        $commentable = $this->commentable();
        $parent = $commentable instanceof Booking
            ? ($commentable->activityTitle() ?? __('a booking'))
            : ($commentable?->summary ?? __('a task'));

        return __(':author commented on :activity.', [
            'author' => $this->comment->author?->name ?? __('someone'),
            'activity' => $parent,
        ]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        return $this->comment->url();
    }

    public function inboxChannel(): ?MailChannel
    {
        $commentable = $this->commentable();

        if ($commentable instanceof Booking) {
            return MailChannel::BookingsComments;
        }

        if ($commentable instanceof Task) {
            return MailChannel::TasksComments;
        }

        return null;
    }

    public function inboxSubject(): ?Model
    {
        return $this->commentable();
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

        return new CommentMail($this->comment, $notifiable);
    }

    public function inboxTeam(?object $notifiable = null): ?Team
    {
        return $this->commentable()?->commentTeam();
    }

    private function commentable(): Booking|Task|null
    {
        $commentable = $this->comment->commentable;

        return $commentable instanceof Booking || $commentable instanceof Task
            ? $commentable
            : null;
    }
}
