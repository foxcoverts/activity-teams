<?php

namespace App\Support;

use App\Filament\Admin\Resources\Keys\KeyResource as AdminKeyResource;
use App\Filament\Admin\Resources\Teams\TeamResource as AdminTeamResource;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Keys\KeyResource as TeamsKeyResource;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Models\Booking;
use App\Models\Key;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class InboundMailResourceLink
{
    public static function label(?Model $resource): ?string
    {
        $name = match (true) {
            $resource instanceof Booking => collect([
                $resource->activityTitle(),
                $resource->audience?->name,
                filled($resource->group_name) ? $resource->group_name : null,
            ])->filter()->implode(' · '),
            $resource instanceof Task => filled($resource->summary) ? (string) $resource->summary : null,
            $resource instanceof Key => filled($resource->name) ? (string) $resource->name : null,
            $resource instanceof Team => filled($resource->name) ? (string) $resource->name : null,
            default => null,
        };

        if ($name === null || $name === '') {
            return null;
        }

        $type = match (true) {
            $resource instanceof Booking => __('Booking'),
            $resource instanceof Task => __('Task'),
            $resource instanceof Key => __('Key'),
            $resource instanceof Team => __('Team'),
            default => null,
        };

        return $type === null ? $name : $type.' · '.$name;
    }

    public static function url(?Model $resource, ?User $viewer = null): ?string
    {
        if ($resource === null) {
            return null;
        }

        if ($viewer instanceof User && ! $viewer->can('view', $resource)) {
            return null;
        }

        return match (true) {
            $resource instanceof Booking => self::bookingUrl($resource),
            $resource instanceof Task => self::taskUrl($resource),
            $resource instanceof Key => self::keyUrl($resource, $viewer),
            $resource instanceof Team => AdminTeamResource::getUrl('edit', ['record' => $resource], panel: 'admin'),
            default => null,
        };
    }

    private static function bookingUrl(Booking $booking): ?string
    {
        $booking->loadMissing('team');
        $team = $booking->team;

        if (! $team instanceof Team) {
            return null;
        }

        return BookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $team);
    }

    private static function taskUrl(Task $task): ?string
    {
        $task->loadMissing('team');
        $team = $task->team;

        if (! $team instanceof Team) {
            return null;
        }

        return TaskResource::getUrl('view', ['record' => $task], panel: 'teams', tenant: $team);
    }

    private static function keyUrl(Key $key, ?User $viewer): ?string
    {
        if ($viewer === null || $viewer->isSystemAdmin()) {
            return AdminKeyResource::getUrl('view', ['record' => $key], panel: 'admin');
        }

        $team = $key->interestedTeams()
            ->first(fn (Team $team): bool => $viewer->manages($team));

        if (! $team instanceof Team) {
            return null;
        }

        return TeamsKeyResource::getUrl('view', ['record' => $key], panel: 'teams', tenant: $team);
    }
}
