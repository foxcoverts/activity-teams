<?php

namespace App\Notifications;

use App\Filament\Member\Resources\KeyRings\KeyRingResource;
use App\Models\KeyRing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyRingPassedToNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public KeyRing $ring, public ?User $from)
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

        return (new MailMessage)
            ->subject(__('You now hold :ring', ['ring' => $this->ring->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You now hold **:ring**. It has been passed to you from **:from**.', [
                'ring' => $this->ring->name,
                'from' => $from,
            ]))
            ->line(__('Please look after the key ring. If you pass it on to someone else please update the website or let a team manager know as soon as possible.'))
            ->action(__('View key rings'), KeyRingResource::getUrl('index', panel: 'member'));
    }
}
