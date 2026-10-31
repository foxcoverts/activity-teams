<?php

namespace App\Notifications;

use App\Enums\MailGroup;
use App\Filament\Member\Resources\Keys\KeyResource;
use App\Models\Key;
use App\Models\User;
use App\Support\InboundMailAddress;
use App\Support\MailPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyPassedToNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Key $ring, public ?User $from)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
