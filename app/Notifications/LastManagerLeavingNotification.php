<?php

namespace App\Notifications;

use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LastManagerLeavingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    /**
     * @param  EloquentCollection<int, Team>  $teams
     */
    public function __construct(public User $member, public EloquentCollection $teams)
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
