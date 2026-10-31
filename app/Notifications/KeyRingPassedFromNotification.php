<?php

namespace App\Notifications;

use App\Enums\MailGroup;
use App\Models\KeyRing;
use App\Models\User;
use App\Support\InboundMailAddress;
use App\Support\MailPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyRingPassedFromNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public KeyRing $ring, public ?User $to)
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
                ->replyTo(InboundMailAddress::forKeyRing($this->ring))
                ->subject(__('You no longer hold :ring', ['ring' => $this->ring->name]))
                ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
                ->line($line)
                ->line(__('If you still have this key ring please contact a team manager as soon as possible.')),
            $notifiable,
            MailGroup::Keys,
        );
    }
}
