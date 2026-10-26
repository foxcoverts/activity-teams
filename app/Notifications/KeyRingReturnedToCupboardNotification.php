<?php

namespace App\Notifications;

use App\Models\KeyRing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyRingReturnedToCupboardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public KeyRing $ring, public ?User $previousHolder)
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
        $mail = (new MailMessage)
            ->subject(__(':ring is in the cupboard', ['ring' => $this->ring->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('**:ring** is back in the cupboard.', ['ring' => $this->ring->name]));

        if ($this->previousHolder instanceof User) {
            $mail->line(__('It was previously held by **:holder**.', [
                'holder' => $this->previousHolder->name,
            ]));
        }

        return $mail;
    }
}
