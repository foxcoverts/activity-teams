<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationDeclinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public TeamInvitation $invitation)
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
        $invitation = $this->invitation->loadMissing(['team', 'user']);

        $line = $invitation->isSiteInvitation()
            ? __(':invitee declined the invitation to become :roles.', [
                'invitee' => $invitation->user->name,
                'roles' => $invitation->siteAccessPhrase(),
            ])
            : __(':invitee declined the invitation to join :team.', [
                'invitee' => $invitation->user->name,
                'team' => $invitation->team->name,
            ]);

        return (new MailMessage)
            ->subject(__('Invitation declined'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($line);
    }
}
