<?php

namespace App\Notifications;

use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailBouncedNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public User $member)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        return __(":name's email address bounced. Update their contact details so they do not miss invitations.", [
            'name' => $this->member->name,
        ]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        if (! $notifiable instanceof User) {
            return null;
        }

        $this->member->loadMissing('teams');

        $team = $this->member->teams
            ->first(fn (Team $team): bool => $notifiable->manages($team));

        if (! $team instanceof Team) {
            return null;
        }

        return MemberResource::getUrl('view', ['record' => $this->member], panel: 'teams', tenant: $team);
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
            'member_id' => $this->member->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        $rebuild = $data['rebuild'];

        return new self(User::withTrashed()->findOrFail($rebuild['member_id']));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->inboxUrl($notifiable);

        $mail = (new MailMessage)
            ->subject(__('Email delivery failed'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(":name's email address bounced. Update their contact details so they do not miss invitations.", [
                'name' => $this->member->name,
            ]));

        if (filled($url)) {
            $mail->action(__('View member'), $url);
        }

        return $mail;
    }
}
