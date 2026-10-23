<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationDeclinedNotification extends Notification
{
    use Queueable;

    public function __construct(public TeamInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invitation = $this->invitation->loadMissing(['team', 'user']);

        return (new MailMessage)
            ->subject(__('Invitation declined'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':invitee declined the invitation to join :team.', [
                'invitee' => $invitation->user->name,
                'team' => $invitation->team->name,
            ]));
    }
}
