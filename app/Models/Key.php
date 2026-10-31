<?php

namespace App\Models;

use Database\Factories\KeyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Key extends Model
{
    /** @use HasFactory<KeyFactory> */
    use HasFactory, HasUlids, LogsActivity;

    public const CUPBOARD = 'cupboard';

    protected $fillable = [
        'name',
        'holder_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    protected static function booted(): void
    {
        static::saving(function (Key $key): void {
            if (! $key->isDirty('holder_id') || $key->holder_id === null) {
                return;
            }

            if (User::query()->whereKey($key->holder_id)->doesntExist()) {
                throw ValidationException::withMessages([
                    'holder_id' => User::leftRecordMessage(),
                ]);
            }
        });
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'holder_id')->withTrashed();
    }

    public function locks(): BelongsToMany
    {
        return $this->belongsToMany(Lock::class, 'key_lock')->orderBy('locks.name');
    }

    public function holderName(): string
    {
        $this->loadMissing('holder');

        return $this->holder?->name ?? __('In the cupboard');
    }

    /**
     * @return list<string>
     */
    public function lockNames(): array
    {
        $this->loadMissing('locks');

        return $this->locks
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function lockDescriptions(): array
    {
        $this->loadMissing('locks.covers');

        return $this->locks
            ->map(function (Lock $lock): string {
                $covers = $lock->covers->sortBy('name')->pluck('name');

                if ($covers->isEmpty()) {
                    return $lock->name;
                }

                return __(':lock, which also opens :covers', [
                    'lock' => $lock->name,
                    'covers' => $covers->join(', '),
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * Locks on this key, plus locks those locks also open.
     *
     * @return list<string>
     */
    public function openedLockIds(): array
    {
        $this->loadMissing('locks.covers');

        return $this->locks
            ->flatMap(fn (Lock $lock): array => [
                (string) $lock->getKey(),
                ...$lock->covers->map(fn (Lock $cover): string => (string) $cover->getKey())->all(),
            ])
            ->unique()
            ->values()
            ->all();
    }

    public function scopeRelevantTo(Builder $keys, Team $team): void
    {
        $keys->where(function (Builder $keys) use ($team): void {
            $keys->whereHas('locks', fn (Builder $locks) => $locks->relevantTo($team))
                ->orWhereHas('locks.covers', fn (Builder $covered) => $covered->relevantTo($team));
        });
    }

    /**
     * Teams whose activities and venues need a lock this key opens.
     *
     * @return EloquentCollection<int, Team>
     */
    public function interestedTeams(): EloquentCollection
    {
        $lockIds = $this->openedLockIds();

        if ($lockIds === []) {
            return new EloquentCollection;
        }

        return Team::query()
            ->whereExists(function ($query) use ($lockIds): void {
                $query->selectRaw('1')
                    ->from('lock_requirements')
                    ->join('activity_team', function ($join): void {
                        $join->on('activity_team.activity_id', '=', 'lock_requirements.activity_id')
                            ->on('activity_team.team_id', '=', 'teams.id');
                    })
                    ->join('team_venue', function ($join): void {
                        $join->on('team_venue.venue_id', '=', 'lock_requirements.venue_id')
                            ->on('team_venue.team_id', '=', 'teams.id');
                    })
                    ->whereIn('lock_requirements.lock_id', $lockIds);
            })
            ->orderBy('name')
            ->get();
    }

    public function canBePassedBy(User $user): bool
    {
        if ($user->trashed() || $user->isPlaceholder() || $user->isSuspended()) {
            return false;
        }

        if ((string) $this->holder_id === (string) $user->getKey()) {
            return true;
        }

        return $this->interestedTeams()->contains(
            fn (Team $team): bool => $user->manages($team),
        );
    }

    /**
     * Current unsuspended members of interested teams, excluding the holder.
     *
     * Placeholders are included the same way booking attendance includes them.
     *
     * @return EloquentCollection<int, User>
     */
    public function passRecipients(): EloquentCollection
    {
        $teamIds = $this->interestedTeams()->modelKeys();

        if ($teamIds === []) {
            return new EloquentCollection;
        }

        return User::query()
            ->where('suspended', false)
            ->whereHas('teams', fn (Builder $teams) => $teams->whereKey($teamIds))
            ->when(
                filled($this->holder_id),
                fn (Builder $users) => $users->whereKeyNot($this->holder_id),
            )
            ->ordered()
            ->get();
    }

    /**
     * @return array<string, string|array<string, string>>
     */
    public function passOptions(): array
    {
        $options = [];

        if ($this->holder_id !== null) {
            $options[__('Cupboard')] = [
                self::CUPBOARD => __('In the cupboard'),
            ];
        }

        $members = $this->passRecipients()
            ->mapWithKeys(fn (User $user): array => [(string) $user->getKey() => $user->name])
            ->all();

        if ($members !== []) {
            $options[__('Members')] = $members;
        }

        return $options;
    }

    public function canBePassedTo(?string $holderId): bool
    {
        if ($holderId === null) {
            return $this->holder_id !== null;
        }

        return $this->passRecipients()->contains(
            fn (User $user): bool => (string) $user->getKey() === (string) $holderId,
        );
    }

    public function hasPassDestination(): bool
    {
        return $this->holder_id !== null || $this->passRecipients()->isNotEmpty();
    }

    /**
     * @return list<string>
     */
    public function requirementSummariesFor(?Team $team = null): array
    {
        $this->loadMissing([
            'locks.requirements.activity',
            'locks.requirements.venue',
            'locks.covers.requirements.activity',
            'locks.covers.requirements.venue',
        ]);

        return $this->locks
            ->concat($this->locks->flatMap->covers)
            ->unique('id')
            ->flatMap(fn (Lock $lock): array => $lock->requirementSummariesFor($team))
            ->unique()
            ->values()
            ->all();
    }
}
