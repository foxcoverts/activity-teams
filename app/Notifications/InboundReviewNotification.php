<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\InboundMails\InboundMailResource;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InboundReviewNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public function __construct(public int $count, public string $watermark)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        return self::sentence($this->count);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        return InboundMailResource::getUrl('index', panel: 'admin');
    }

    public function inboxAlwaysEmailable(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function inboxRebuild(): array
    {
        return [
            'count' => $this->count,
            'watermark' => $this->watermark,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        $rebuild = $data['rebuild'];

        return new self((int) $rebuild['count'], (string) $rebuild['watermark']);
    }

    public static function sentence(int $count): string
    {
        return trans_choice(':count message needs review.|:count messages need review.', $count, [
            'count' => $count,
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(self::sentence($this->count))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(self::sentence($this->count))
            ->action(__('Review inbound mail'), InboundMailResource::getUrl('index', panel: 'admin'));
    }
}
