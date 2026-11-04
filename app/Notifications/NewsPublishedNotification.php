<?php

namespace App\Notifications;

use App\Enums\MailChannel;
use App\Mail\NewsPostMail;
use App\Models\NewsPost;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class NewsPublishedNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public NewsPost $post)
    {
        $this->afterCommit();
        $this->post->loadMissing(['team', 'author']);
    }

    public function inboxBody(object $notifiable): string
    {
        return __(':author published “:title” for :team.', [
            'author' => $this->post->author?->name ?? __('someone'),
            'title' => $this->post->title,
            'team' => $this->post->team?->name ?? __('a team'),
        ]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        return $this->post->memberUrl();
    }

    public function inboxChannel(): ?MailChannel
    {
        return MailChannel::NewsMail;
    }

    public function inboxSubject(): ?NewsPost
    {
        return $this->post;
    }

    /**
     * @return array<string, mixed>
     */
    public function inboxRebuild(): array
    {
        return [
            'news_post_id' => $this->post->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        $rebuild = $data['rebuild'];

        return new self(NewsPost::query()->findOrFail($rebuild['news_post_id']));
    }

    public function toMail(object $notifiable): Mailable
    {
        assert($notifiable instanceof User);

        return new NewsPostMail($this->post, $notifiable);
    }
}
