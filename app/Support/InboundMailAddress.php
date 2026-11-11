<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Key;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InboundMailAddress
{
    public const BOOKING = 'booking';

    public const KEY = 'key';

    public const TEAMS = 'teams';

    public const TASK = 'task';

    public static function domain(): string
    {
        $domain = (string) config('mail.inbound_domain');

        if ($domain !== '') {
            return $domain;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'activity-teams.local';

        return $host === 'localhost' ? 'activity-teams.local' : $host;
    }

    public static function forBooking(Booking $booking): string
    {
        return self::make(self::BOOKING, $booking->getKey());
    }

    public static function forKey(Key $ring): string
    {
        return self::make(self::KEY, $ring->getKey());
    }

    public static function forTeam(Team $team): string
    {
        return self::make(self::TEAMS, $team->getKey());
    }

    public static function forTask(Task $task): string
    {
        return self::make(self::TASK, $task->getKey());
    }

    public static function make(string $localPart, string $id): string
    {
        return sprintf('%s+%s@%s', $localPart, $id, self::domain());
    }

    /**
     * @return array{type: string, id: string}|null
     */
    public static function parse(string $address): ?array
    {
        $address = strtolower(trim($address));
        $address = Str::after($address, 'mailto:');

        if (! str_contains($address, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $address, 2);

        if ($domain !== strtolower(self::domain()) || ! str_contains($local, '+')) {
            return null;
        }

        [$type, $id] = explode('+', $local, 2);

        if ($type === '' || $id === '') {
            return null;
        }

        if (! in_array($type, [self::BOOKING, self::KEY, self::TEAMS, self::TASK], true)) {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }

    public static function findResource(string $address): Booking|Key|Task|Team|null
    {
        $parts = self::parse($address);

        if ($parts === null) {
            return null;
        }

        return match ($parts['type']) {
            self::BOOKING => Booking::query()->find($parts['id']),
            self::KEY => Key::query()->find($parts['id']),
            self::TASK => Task::query()->find($parts['id']),
            self::TEAMS => Team::query()->find($parts['id']),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function managerEmailsFor(Model $resource): array
    {
        $managers = match (true) {
            $resource instanceof Booking => $resource->team?->managers ?? collect(),
            $resource instanceof Task => $resource->team?->managers ?? collect(),
            $resource instanceof Team => $resource->managers,
            $resource instanceof Key => $resource->interestedTeams()
                ->loadMissing('managers')
                ->flatMap(fn (Team $team) => $team->managers),
            default => collect(),
        };

        return $managers
            ->filter(fn ($user) => filled($user->email) && ! $user->isSuspended())
            ->unique('id')
            ->pluck('email')
            ->map(fn (string $email): string => strtolower($email))
            ->values()
            ->all();
    }
}
