<?php

namespace App\Models;

use Database\Factories\KeyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Key extends Model
{
    /** @use HasFactory<KeyFactory> */
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
        return $this->hasMany(KeyRequirement::class)->orderBy('created_at');
    }

    public function covers(): BelongsToMany
    {
        return $this->belongsToMany(Key::class, 'key_covers', 'key_id', 'covered_key_id')
            ->using(KeyCover::class)
            ->orderBy('keys.name');
    }

    public function coveredBy(): BelongsToMany
    {
        return $this->belongsToMany(Key::class, 'key_covers', 'covered_key_id', 'key_id')
            ->using(KeyCover::class)
            ->orderBy('keys.name');
    }

    public function rings(): BelongsToMany
    {
        return $this->belongsToMany(KeyRing::class, 'key_ring_key')->orderBy('key_rings.name');
    }

    public function scopeRelevantTo(Builder $keys, Team $team): void
    {
        $keys->whereHas('requirements', function (Builder $requirements) use ($team): void {
            $requirements
                ->whereIn('activity_id', $team->activities()->select('activities.id'))
                ->whereIn('venue_id', $team->venues()->select('venues.id'));
        });
    }

    public function scopeOpenedBy(Builder $keys, User $user): void
    {
        $id = $user->getKey();

        $keys->where(function (Builder $keys) use ($id): void {
            $keys->whereHas('rings', fn (Builder $rings) => $rings->where('holder_id', $id))
                ->orWhereHas('coveredBy.rings', fn (Builder $rings) => $rings->where('holder_id', $id));
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
                fn (KeyRequirement $requirement): bool => in_array($requirement->activity_id, $activityIds, true)
                    && in_array($requirement->venue_id, $venueIds, true),
            );
        }

        return $requirements->map->summary()->values()->all();
    }

    public function accessRings(): Builder
    {
        $id = $this->getKey();

        return KeyRing::query()
            ->where(function (Builder $rings) use ($id): void {
                $rings->whereHas('keys', fn (Builder $keys) => $keys->whereKey($id))
                    ->orWhereHas('keys.covers', fn (Builder $covered) => $covered->whereKey($id));
            })
            ->orderBy('name');
    }
}
