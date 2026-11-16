<?php

namespace App\Notifications\Channels;

use App\Models\Team;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

class InboxDatabaseChannel extends DatabaseChannel
{
    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        $payload = parent::buildPayload($notifiable, $notification);

        if (! method_exists($notification, 'inboxTeam')) {
            return $payload;
        }

        $team = $notification->inboxTeam($notifiable);

        if (! $team instanceof Team) {
            return $payload;
        }

        $payload['team_id'] = $team->getKey();

        return $payload;
    }
}
