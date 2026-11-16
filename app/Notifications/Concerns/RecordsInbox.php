<?php

namespace App\Notifications\Concerns;

use App\Enums\MailChannel;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;

trait RecordsInbox
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['inbox-database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $subject = $this->inboxSubject();
        $team = $this->inboxTeam($notifiable);

        $payload = [
            'body' => $this->inboxBody($notifiable),
            'url' => $this->inboxUrl($notifiable),
            'mail_channel' => $this->inboxChannel()?->value,
            'always_emailable' => $this->inboxAlwaysEmailable(),
            'in_app_only' => $this->inboxInAppOnly(),
            'subject_type' => $subject instanceof Model ? $subject::class : null,
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'rebuild' => $this->inboxRebuild(),
        ];

        if ($team instanceof Team) {
            $payload['team_id'] = $team->getKey();
        }

        return $payload;
    }

    public function inboxTeam(?object $notifiable = null): ?Team
    {
        return null;
    }

    abstract public function inboxBody(object $notifiable): string;

    /**
     * Destination for this notice. Every sent notice returns a URL. Do not send from a branch that would return null. `ViewNotification` marks the row read and redirects here, and does not render the body. A notice that cannot have a destination needs that page to show the message instead.
     */
    abstract public function inboxUrl(object $notifiable): ?string;

    /**
     * @return array<string, mixed>
     */
    abstract public function inboxRebuild(): array;

    public function inboxChannel(): ?MailChannel
    {
        return null;
    }

    public function inboxAlwaysEmailable(): bool
    {
        return false;
    }

    public function inboxInAppOnly(): bool
    {
        return false;
    }

    public function inboxSubject(): ?Model
    {
        return null;
    }
}
