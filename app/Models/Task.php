<?php

namespace App\Models;

use App\Casts\AsSequence;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Support\Coords;
use App\Support\Location;
use Database\Factories\TaskFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'venue_id',
        'summary',
        'description',
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

    public ?string $privateVenueIdForDeletion = null;

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

            $team = Team::query()->find($task->team_id);

            if ($team === null) {
                return;
            }

            $venueAssignmentChanging = ! $task->exists
                || $task->isDirty(['venue_id', 'team_id']);

            if ($venueAssignmentChanging && filled($task->venue_id)) {
                $hasCatalogVenue = $team->venues()->whereKey($task->venue_id)->exists();
                $hasPrivateVenue = $task->exists
                    && Venue::onlyPrivate()
                        ->whereKey($task->venue_id)
                        ->where('task_id', $task->getKey())
                        ->exists();

                if (! $hasCatalogVenue && ! $hasPrivateVenue) {
                    throw ValidationException::withMessages([
                        'venue_id' => __('Choose a venue this team has been given.'),
                    ]);
                }
            }

            $task->syncCompletionTimestamps();
        });

        static::deleting(function (Task $task): void {
            if (
                Venue::onlyPrivate()
                    ->whereKey($task->venue_id)
                    ->where('task_id', $task->getKey())
                    ->exists()
            ) {
                $task->privateVenueIdForDeletion = (string) $task->venue_id;
            }
        });

        static::deleted(function (Task $task): void {
            if ($task->privateVenueIdForDeletion !== null) {
                Venue::withPrivate()->find($task->privateVenueIdForDeletion)?->delete();
            }
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
            'venue_id',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class)->withoutGlobalScope('catalog');
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

    /**
     * @return Attribute<?Location, never>
     */
    protected function location(): Attribute
    {
        return Attribute::make(
            get: fn (): ?Location => $this->venue?->location,
        );
    }

    /**
     * @return Attribute<?Coords, never>
     */
    protected function coords(): Attribute
    {
        return Attribute::make(
            get: fn (): ?Coords => $this->venue?->coords,
        );
    }

    public function isComplete(): bool
    {
        return $this->status === TaskStatus::Completed
            || $this->completed_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->status === TaskStatus::Cancelled;
    }

    public function isOverdue(): bool
    {
        if ($this->isComplete() || $this->isCancelled() || $this->due_at === null) {
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

    public function markStarted(): void
    {
        $this->status = TaskStatus::InProcess;
        $this->started_at ??= now();
        $this->completed_at = null;
        $this->save();
    }

    public function markCancelled(): void
    {
        $this->status = TaskStatus::Cancelled;
        $this->save();
    }

    public function reopen(): void
    {
        $this->status = TaskStatus::NeedsAction;
        $this->completed_at = null;
        $this->save();
    }

    public function toggleCompletion(): void
    {
        if ($this->isComplete() || $this->isCancelled()) {
            $this->reopen();

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
