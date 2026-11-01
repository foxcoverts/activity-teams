<?php

namespace App\Notifications;

use App\Filament\Admin\Resources\Teams\TeamResource;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LastManagerLeavingNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  EloquentCollection<int, Team>  $teams
     */
    public function __construct(public User $member, public EloquentCollection $teams)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        return __(':member was the last manager of :teams and has deleted their account.', [
            'member' => $this->member->name,
            'teams' => $this->teams->pluck('name')->join(', '),
        ]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        return TeamResource::getUrl('index', [
            'filters' => [
                'managers.id' => ['value' => '__empty'],
            ],
        ], panel: 'admin');
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
            'team_ids' => $this->teams->modelKeys(),
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
            Team::query()->whereKey($rebuild['team_ids'])->get(),
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $names = $this->teams->pluck('name')->join(', ');

        return (new MailMessage)
            ->subject(__('Last manager left a team'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':member was the last manager of :teams and has deleted their account.', [
                'member' => $this->member->name,
                'teams' => $names,
            ]));
    }
}
