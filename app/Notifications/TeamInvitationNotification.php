<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TeamInvitationNotification extends Notification
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
            ->subject(__('You have been invited to :team', ['team' => $invitation->team->name]))
            ->greeting(__('Hello :name,', [
                'name' => $invitation->user->name,
            ]))
            ->line(__('You have been invited to join :team.', [
                'team' => $invitation->team->name,
            ]))
            ->action(__('View invitation'), URL::temporarySignedRoute(
                'invitation.show',
                $invitation->expires_at,
                ['invitation' => $invitation, 'token' => $invitation->token],
            ))
            ->line(__('This invitation expires on :date.', ['date' => $invitation->expires_at->format('j F Y')]));
    }
}
