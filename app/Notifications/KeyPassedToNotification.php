<?php

namespace App\Notifications;

use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Filament\Member\Resources\Keys\KeyResource;
use App\Models\Key;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use App\Support\InboundMailAddress;
use App\Support\MailPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyPassedToNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Key $ring, public ?User $from)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        $from = $this->from instanceof User ? $this->from->name : __('the cupboard');

        return __('You now hold :ring. It was passed to you from :from.', [
            'ring' => $this->ring->name,
            'from' => $from,
        ]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        return KeyResource::getUrl('view', ['record' => $this->ring], panel: 'member');
    }

    public function inboxChannel(): ?MailChannel
    {
        return MailChannel::KeysMail;
    }

    /**
     * @return array<string, mixed>
     */
    public function inboxRebuild(): array
    {
        return [
            'key_id' => $this->ring->getKey(),
            'from_id' => $this->from?->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        $rebuild = $data['rebuild'];

        return new self(
            Key::query()->findOrFail($rebuild['key_id']),
            filled($rebuild['from_id'] ?? null) ? User::withTrashed()->find($rebuild['from_id']) : null,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $from = $this->from instanceof User
            ? $this->from->name
            : __('the cupboard');

        assert($notifiable instanceof User);

        return MailPreference::withFooter(
            (new MailMessage)
                ->replyTo(InboundMailAddress::forKey($this->ring))
                ->subject(__('You now hold :ring', ['ring' => $this->ring->name]))
                ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
                ->line(__('You now hold **:ring**. It has been passed to you from **:from**.', [
                    'ring' => $this->ring->name,
                    'from' => $from,
                ]))
                ->line(__('Please look after the key. If you pass it on to someone else please update the website or let a team manager know as soon as possible.'))
                ->action(__('View keys'), KeyResource::getUrl('index', panel: 'member')),
            $notifiable,
            MailGroup::Keys,
        );
    }
}
