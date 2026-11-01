<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Models\TeamInvitation;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationDeclinedNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public TeamInvitation $invitation)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        $invitation = $this->invitation->loadMissing(['team', 'user']);

        if ($invitation->isSiteInvitation()) {
            return __(':invitee declined the invitation to become :roles.', [
                'invitee' => $invitation->user->name,
                'roles' => $invitation->siteAccessPhrase(),
            ]);
        }

        return __(':invitee declined the invitation to join :team.', [
            'invitee' => $invitation->user->name,
            'team' => $invitation->team->name,
        ]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        $invitation = $this->invitation->loadMissing(['team', 'user']);

        if ($invitation->isSiteInvitation() || $invitation->team === null) {
            return UserResource::getUrl('view', ['record' => $invitation->user], panel: 'admin');
        }

        return MemberResource::getUrl('view', ['record' => $invitation->user], panel: 'manage', tenant: $invitation->team);
    }

    public function inboxAlwaysEmailable(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function inboxRebuild(): array
    {
        return [
            'invitation_id' => $this->invitation->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        return new self(TeamInvitation::query()->findOrFail($data['rebuild']['invitation_id']));
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
