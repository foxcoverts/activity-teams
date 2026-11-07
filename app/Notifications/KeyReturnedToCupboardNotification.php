<?php

namespace App\Notifications;

use App\Enums\MailChannel;
use App\Filament\Teams\Resources\Keys\KeyResource;
use App\Models\Key;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyReturnedToCupboardNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Key $ring, public ?User $previousHolder)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        if ($this->previousHolder instanceof User) {
            return __(':ring is back in the cupboard. It was previously held by :holder.', [
                'ring' => $this->ring->name,
                'holder' => $this->previousHolder->name,
            ]);
        }

        return __(':ring is back in the cupboard.', ['ring' => $this->ring->name]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        if (! $notifiable instanceof User) {
            return null;
        }

        $team = $this->ring->interestedTeams()->first(
            fn (Team $team): bool => $notifiable->manages($team),
        );

        if (! $team instanceof Team) {
            return null;
        }

        return KeyResource::getUrl('view', ['record' => $this->ring], panel: 'teams', tenant: $team);
    }

    public function inboxChannel(): ?MailChannel
    {
        return MailChannel::KeysMail;
    }

    /**
     * @return array<string, mixed>
     */
    public function inboxRebuild(): array
    {
        return [
            'key_id' => $this->ring->getKey(),
            'previous_holder_id' => $this->previousHolder?->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        $rebuild = $data['rebuild'];

        return new self(
            Key::query()->findOrFail($rebuild['key_id']),
            filled($rebuild['previous_holder_id'] ?? null)
                ? User::withTrashed()->find($rebuild['previous_holder_id'])
                : null,
        );
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
