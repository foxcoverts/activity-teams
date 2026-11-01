<?php

namespace App\Notifications;

use App\Filament\Manage\Resources\Keys\KeyResource;
use App\Models\Key;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KeyHolderLeavingNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  EloquentCollection<int, Key>  $keys
     */
    public function __construct(
        public User $member,
        public EloquentCollection $keys,
        public ?Team $team = null,
    ) {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        return __(':member has deleted their account and still holds :keys. The keys stay assigned.', [
            'member' => $this->member->name,
            'keys' => $this->keys->pluck('name')->join(', '),
        ]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        if (! $this->team instanceof Team) {
            return null;
        }

        return KeyResource::getUrl('index', [
            'filters' => [
                'holder_id' => ['value' => $this->member->getKey()],
            ],
        ], panel: 'manage', tenant: $this->team);
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
            'key_ids' => $this->keys->modelKeys(),
            'team_id' => $this->team?->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        $rebuild = $data['rebuild'];

        return new self(
            User::withTrashed()->findOrFail($rebuild['member_id']),
            Key::query()->whereKey($rebuild['key_ids'])->get(),
            filled($rebuild['team_id'] ?? null) ? Team::query()->find($rebuild['team_id']) : null,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Keys still assigned'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':member has deleted their account and still holds :keys. The keys stay assigned.', [
                'member' => $this->member->name,
                'keys' => $this->keys->pluck('name')->join(', '),
            ]));
    }
}
