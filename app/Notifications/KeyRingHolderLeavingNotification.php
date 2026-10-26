<?php

namespace App\Notifications;

use App\Models\KeyRing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyRingHolderLeavingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  EloquentCollection<int, KeyRing>  $keyRings
     */
    public function __construct(public User $member, public EloquentCollection $keyRings)
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
        $names = $this->keyRings->pluck('name')->join(', ');

        return (new MailMessage)
            ->subject(__('Key rings still assigned'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':member has deleted their account and still holds :rings. The rings stay assigned.', [
                'member' => $this->member->name,
                'rings' => $names,
            ]));
    }
}
