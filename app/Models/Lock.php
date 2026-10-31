<?php

namespace App\Models;

use Database\Factories\LockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Lock extends Model
{
    /** @use HasFactory<LockFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'name',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(LockRequirement::class)->orderBy('created_at');
    }

    public function covers(): BelongsToMany
    {
        return $this->belongsToMany(Lock::class, 'lock_covers', 'lock_id', 'covered_lock_id')
            ->using(LockCover::class)
            ->orderBy('locks.name');
    }

    public function coveredBy(): BelongsToMany
    {
        return $this->belongsToMany(Lock::class, 'lock_covers', 'covered_lock_id', 'lock_id')
            ->using(LockCover::class)
            ->orderBy('locks.name');
    }

    public function keys(): BelongsToMany
    {
        return $this->belongsToMany(Key::class, 'key_lock')->orderBy('keys.name');
    }

    public function scopeRelevantTo(Builder $locks, Team $team): void
    {
        $locks->whereHas('requirements', function (Builder $requirements) use ($team): void {
            $requirements
                ->whereIn('activity_id', $team->activities()->select('activities.id'))
                ->whereIn('venue_id', $team->venues()->select('venues.id'));
        });
    }

    public function scopeOpenedBy(Builder $locks, User $user): void
    {
        $id = $user->getKey();

        $locks->where(function (Builder $locks) use ($id): void {
            $locks->whereHas('keys', fn (Builder $keys) => $keys->where('holder_id', $id))
                ->orWhereHas('coveredBy.keys', fn (Builder $keys) => $keys->where('holder_id', $id));
        });
    }

    /**
     * @return list<string>
     */
    public function requirementSummariesFor(?Team $team = null): array
    {
        $this->loadMissing('requirements.activity', 'requirements.venue');

        $requirements = $this->requirements;

        if ($team instanceof Team) {
            $activityIds = once(fn (): array => $team->activities()->pluck('activities.id')->all());
            $venueIds = once(fn (): array => $team->venues()->pluck('venues.id')->all());
            $requirements = $requirements->filter(
                fn (LockRequirement $requirement): bool => in_array($requirement->activity_id, $activityIds, true)
                    && in_array($requirement->venue_id, $venueIds, true),
            );
        }

        return $requirements->map->summary()->values()->all();
    }

    public function accessKeys(): Builder
    {
        $id = $this->getKey();

        return Key::query()
            ->where(function (Builder $keys) use ($id): void {
                $keys->whereHas('locks', fn (Builder $locks) => $locks->whereKey($id))
                    ->orWhereHas('locks.covers', fn (Builder $covered) => $covered->whereKey($id));
            })
            ->orderBy('name');
    }
}
