<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use App\Support\InboundMailAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TeamInvitationNotification extends Notification implements ShouldQueue
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

        $subject = $invitation->isSiteInvitation()
            ? __('You have been invited to :app', ['app' => config('app.name')])
            : __('You have been invited to :team', ['team' => $invitation->team->name]);

        $line = $invitation->isSiteInvitation()
            ? __('You have been invited as :roles.', ['roles' => $invitation->siteAccessPhrase()])
            : __('You have been invited to join :team.', ['team' => $invitation->team->name]);

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting(__('Hello :name,', [
                'name' => $invitation->user->name,
            ]))
            ->line($line)
            ->action(__('View invitation'), URL::temporarySignedRoute(
                'invitation.show',
                $invitation->expires_at,
                ['invitation' => $invitation, 'token' => $invitation->token],
            ))
            ->line(__('This invitation expires on :date.', ['date' => $invitation->expires_at->format('j F Y')]));

        if (! $invitation->isSiteInvitation() && $invitation->team !== null) {
            $message->replyTo(InboundMailAddress::forTeam($invitation->team));
        }

        return $message;
    }
}
