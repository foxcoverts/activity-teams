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

class KeyPassedFromNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Key $ring, public ?User $to)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        if ($this->to instanceof User) {
            return __('You no longer hold :ring. It was passed to :to.', [
                'ring' => $this->ring->name,
                'to' => $this->to->name,
            ]);
        }

        return __('You no longer hold :ring. It is in the cupboard.', [
            'ring' => $this->ring->name,
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
            'to_id' => $this->to?->getKey(),
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
            filled($rebuild['to_id'] ?? null) ? User::withTrashed()->find($rebuild['to_id']) : null,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->to instanceof User
            ? __('You no longer hold **:ring**. It has been passed to **:to**.', [
                'ring' => $this->ring->name,
                'to' => $this->to->name,
            ])
            : __('You no longer hold **:ring**. It is in the cupboard.', [
                'ring' => $this->ring->name,
            ]);

        assert($notifiable instanceof User);

        return MailPreference::withFooter(
            (new MailMessage)
                ->replyTo(InboundMailAddress::forKey($this->ring))
                ->subject(__('You no longer hold :ring', ['ring' => $this->ring->name]))
                ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
                ->line($line)
                ->line(__('If you still have this key please contact a team manager as soon as possible.')),
            $notifiable,
            MailGroup::Keys,
        );
    }
}
