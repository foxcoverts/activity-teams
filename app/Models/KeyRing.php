<?php

namespace App\Models;

use Database\Factories\KeyRingFactory;
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

class KeyRing extends Model
{
    /** @use HasFactory<KeyRingFactory> */
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
        static::saving(function (KeyRing $ring): void {
            if (! $ring->isDirty('holder_id') || $ring->holder_id === null) {
                return;
            }

            if (User::query()->whereKey($ring->holder_id)->doesntExist()) {
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

    public function keys(): BelongsToMany
    {
        return $this->belongsToMany(Key::class, 'key_ring_key')->orderBy('keys.name');
    }

    public function holderName(): string
    {
        $this->loadMissing('holder');

        return $this->holder?->name ?? __('In the cupboard');
    }

    /**
     * @return list<string>
     */
    public function keyNames(): array
    {
        $this->loadMissing('keys');

        return $this->keys
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function keyDescriptions(): array
    {
        $this->loadMissing('keys.covers');

        return $this->keys
            ->map(function (Key $key): string {
                $covers = $key->covers->sortBy('name')->pluck('name');

                if ($covers->isEmpty()) {
                    return $key->name;
                }

                return __(':key, which also opens :covers', [
                    'key' => $key->name,
                    'covers' => $covers->join(', '),
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * Keys on this ring, plus keys those keys cover.
     *
     * @return list<string>
     */
    public function openedKeyIds(): array
    {
        $this->loadMissing('keys.covers');

        return $this->keys
            ->flatMap(fn (Key $key): array => [
                (string) $key->getKey(),
                ...$key->covers->map(fn (Key $cover): string => (string) $cover->getKey())->all(),
            ])
            ->unique()
            ->values()
            ->all();
    }

    public function scopeRelevantTo(Builder $rings, Team $team): void
    {
        $rings->where(function (Builder $rings) use ($team): void {
            $rings->whereHas('keys', fn (Builder $keys) => $keys->relevantTo($team))
                ->orWhereHas('keys.covers', fn (Builder $covered) => $covered->relevantTo($team));
        });
    }

    /**
     * Teams whose activities and venues need a key this ring opens.
     *
     * @return EloquentCollection<int, Team>
     */
    public function interestedTeams(): EloquentCollection
    {
        $keyIds = $this->openedKeyIds();

        if ($keyIds === []) {
            return new EloquentCollection;
        }

        return Team::query()
            ->whereExists(function ($query) use ($keyIds): void {
                $query->selectRaw('1')
                    ->from('key_requirements')
                    ->join('activity_team', function ($join): void {
                        $join->on('activity_team.activity_id', '=', 'key_requirements.activity_id')
                            ->on('activity_team.team_id', '=', 'teams.id');
                    })
                    ->join('team_venue', function ($join): void {
                        $join->on('team_venue.venue_id', '=', 'key_requirements.venue_id')
                            ->on('team_venue.team_id', '=', 'teams.id');
                    })
                    ->whereIn('key_requirements.key_id', $keyIds);
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
            'keys.requirements.activity',
            'keys.requirements.venue',
            'keys.covers.requirements.activity',
            'keys.covers.requirements.venue',
        ]);

        return $this->keys
            ->concat($this->keys->flatMap->covers)
            ->unique('id')
            ->flatMap(fn (Key $key): array => $key->requirementSummariesFor($team))
            ->unique()
            ->values()
            ->all();
    }
}
