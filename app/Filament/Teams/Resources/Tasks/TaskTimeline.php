<?php

namespace App\Filament\Teams\Resources\Tasks;

use App\Enums\TimelineAudience;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Support\Palette;
use App\Support\TimelineView;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component as LivewireComponent;
use RalphJSmit\Filament\Activitylog\Filament\Infolists\Components\Timeline;
use Spatie\Activitylog\Models\Activity as ActivityLog;

class TaskTimeline
{
    public static function section(
        TimelineAudience $audience,
        bool $showAudienceToggle = false,
        ?LivewireComponent $livewire = null,
        ?Task $task = null,
        ?User $viewer = null,
    ): Section {
        TimelineView::set($audience, $viewer, booking: null, task: $task);

        $section = Section::make(__('Recent activity'))
            ->key('task-recent-activity-'.$audience->value)
            ->columnSpanFull()
            ->components([
                static::entry($audience)->hiddenLabel(),
            ]);

        if ($showAudienceToggle && $livewire !== null && method_exists($livewire, 'toggleTimelineAudience')) {
            $section->headerActions([
                Action::make('viewTimelineAsMember')
                    ->label(fn (): string => $livewire->timelineAsMember
                        ? __('Viewing as member')
                        : __('View as member'))
                    ->icon(Heroicon::OutlinedEye)
                    ->link()
                    ->color(fn (): string|array => $livewire->timelineAsMember
                        ? Palette::uncertain()
                        : Palette::neutral())
                    ->action(fn () => $livewire->toggleTimelineAudience()),
            ]);
        }

        return $section;
    }

    public static function entry(TimelineAudience $audience): Timeline
    {
        $timeline = Timeline::make()
            ->columnSpanFull()
            ->causerUrl(fn (?Model $causer, ActivityLog $activity): ?string => $audience->isManager()
                ? static::memberUrl($causer, $activity)
                : null)
            ->recordUrl(Task::class, fn (): null => null)
            ->recordUrl(User::class, fn (User $user, ActivityLog $activity): ?string => $audience->isManager()
                ? static::memberUrl($user, $activity)
                : null)
            ->recordUrl(Venue::class, fn (): null => null)
            ->recordUrl(Team::class, fn (): null => null);

        if ($audience->isMember()) {
            $timeline->modifyActivitiesQueryUsing(function (Builder $query): Builder {
                return $query->whereNot(function (Builder $hidden): void {
                    $hidden->where('event', 'assignee_cleared')
                        ->orWhere(function (Builder $invited): void {
                            $invited->where('event', 'assignee_assigned')
                                ->where('properties->invite', true);
                        });
                });
            });
        }

        return $timeline;
    }

    protected static function memberUrl(?Model $causer, ActivityLog $activity): ?string
    {
        if (! $causer instanceof User) {
            return null;
        }

        return MemberResource::personUrlFor($causer, static::teamFor($activity));
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
