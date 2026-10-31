<?php

namespace App\Notifications;

use App\Models\Key;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyHolderLeavingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  EloquentCollection<int, Key>  $keys
     */
    public function __construct(public User $member, public EloquentCollection $keys)
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
        $names = $this->keys->pluck('name')->join(', ');

        return (new MailMessage)
            ->subject(__('Keys still assigned'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':member has deleted their account and still holds :keys. The keys stay assigned.', [
                'member' => $this->member->name,
                'keys' => $names,
            ]));
    }
}
