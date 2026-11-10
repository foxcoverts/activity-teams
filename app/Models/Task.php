<?php

namespace App\Models;

use App\Casts\AsSequence;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use Concerns\HasSequence, Concerns\HasUid, HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'team_id',
        'summary',
        'description',
        'location',
        'priority',
        'status',
        'due_at',
        'started_at',
        'completed_at',
    ];

    protected $attributes = [
        'status' => TaskStatus::NeedsAction->value,
        'priority' => TaskPriority::Medium->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'sequence' => AsSequence::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            $tenant = Filament::getTenant();

            if (blank($task->team_id) && $tenant instanceof Team) {
                $task->team_id = $tenant->getKey();
            }

            if (blank($task->team_id)) {
                throw ValidationException::withMessages([
                    'team_id' => __('Choose a team.'),
                ]);
            }

            $task->summary = trim((string) $task->summary);
            $task->description = filled($task->description) ? trim((string) $task->description) : null;
            $task->location = filled($task->location) ? trim((string) $task->location) : null;

            $task->syncCompletionTimestamps();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return list<string>
     */
    protected function sequenced(): array
    {
        return [
            'status',
            'due_at',
            'started_at',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_task');
    }

    public function attachBooking(Booking $booking): void
    {
        if ($booking->team_id !== $this->team_id) {
            throw ValidationException::withMessages([
                'booking_id' => __('Choose a booking on the same team.'),
            ]);
        }

        $this->bookings()->syncWithoutDetaching([$booking->getKey()]);
    }

    public function detachBooking(Booking $booking): void
    {
        $this->bookings()->detach($booking->getKey());
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null
            || in_array($this->status, [TaskStatus::Completed, TaskStatus::Cancelled], true);
    }

    public function isOverdue(): bool
    {
        if ($this->isComplete() || $this->due_at === null) {
            return false;
        }

        return $this->due_at->isPast();
    }

    public function markCompleted(): void
    {
        $this->status = TaskStatus::Completed;
        $this->completed_at ??= now();
        $this->started_at ??= $this->completed_at;
        $this->save();
    }

    public function markIncomplete(): void
    {
        $this->status = TaskStatus::NeedsAction;
        $this->completed_at = null;
        $this->save();
    }

    public function toggleCompletion(): void
    {
        if ($this->status === TaskStatus::Cancelled) {
            return;
        }

        if ($this->isComplete()) {
            $this->markIncomplete();

            return;
        }

        $this->markCompleted();
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            TaskStatus::NeedsAction,
            TaskStatus::InProcess,
        ]);
    }

    /**
     * Default ordering for non-table lists. Do not apply as a global scope.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeOrderedForList(Builder $query): void
    {
        $query
            ->orderByRaw('CASE WHEN completed_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('priority')
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at');
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->where('team_id', $team->getKey());
    }

    /**
     * Tasks visible to a user across tasks-enabled teams they currently belong to.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('team', function (Builder $teams) use ($user): void {
            $teams->where('features->tasks', true);

            if (! $user->isSystemAdmin()) {
                $teams->whereHas(
                    'members',
                    fn (Builder $members): Builder => $members->whereKey($user->getKey()),
                );
            }
        });
    }

    private function syncCompletionTimestamps(): void
    {
        if (! $this->status instanceof TaskStatus) {
            return;
        }

        if ($this->status === TaskStatus::Completed) {
            $this->completed_at ??= now();
            $this->started_at ??= $this->completed_at;

            return;
        }

        if ($this->status === TaskStatus::Cancelled) {
            return;
        }

        if ($this->status === TaskStatus::InProcess) {
            $this->started_at ??= now();
        }

        $this->completed_at = null;
    }
}
