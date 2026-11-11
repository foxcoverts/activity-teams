<?php

namespace App\Filament\Teams\Resources\Tasks;

use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use RalphJSmit\Filament\Activitylog\Filament\Infolists\Components\Timeline;
use Spatie\Activitylog\Models\Activity as ActivityLog;

class TaskTimeline
{
    public static function section(): Section
    {
        return Section::make(__('Recent activity'))
            ->columnSpanFull()
            ->components([
                static::entry()->hiddenLabel(),
            ]);
    }

    public static function entry(): Timeline
    {
        return Timeline::make()
            ->columnSpanFull()
            ->causerUrl(fn (?Model $causer, ActivityLog $activity): ?string => static::memberUrl($causer, $activity))
            ->recordUrl(Task::class, fn (): null => null)
            ->recordUrl(User::class, fn (User $user, ActivityLog $activity): ?string => static::memberUrl($user, $activity))
            ->recordUrl(Venue::class, fn (): null => null)
            ->recordUrl(Team::class, fn (): null => null);
    }

    protected static function memberUrl(?Model $causer, ActivityLog $activity): ?string
    {
        if (! $causer instanceof User) {
            return null;
        }

        $team = static::teamFor($activity);

        if (! $team instanceof Team || ! $causer->teams()->whereKey($team->getKey())->exists()) {
            return null;
        }

        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return null;
        }

        $mayLinkMembers = $viewer->manages($team)
            || ($viewer->isSystemAdmin() && MemberResource::canView($causer));

        if (! $mayLinkMembers || ! MemberResource::canView($causer)) {
            return null;
        }

        return MemberResource::getUrl('view', ['record' => $causer], panel: 'teams', tenant: $team);
    }

    protected static function teamFor(ActivityLog $activity): ?Team
    {
        $subject = $activity->relationLoaded('subject') ? $activity->subject : null;

        if (! $subject instanceof Task && $activity->subject_type === Task::class) {
            $subject = Task::query()->find($activity->subject_id);
        }

        if ($subject instanceof Task) {
            return static::teamOf($subject);
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }

    protected static function teamOf(Task $task): ?Team
    {
        if ($task->relationLoaded('team')) {
            return $task->team;
        }

        return Team::query()->find($task->team_id);
    }
}
