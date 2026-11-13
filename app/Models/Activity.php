<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'requires_activity_label',
    ];

    protected $attributes = [
        'requires_activity_label' => false,
    ];

    protected function casts(): array
    {
        return [
            'requires_activity_label' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Activity $activity): void {
            $activity->teams()
                ->where('teams.default_activity_id', $activity->getKey())
                ->get()
                ->each(fn (Team $team) => $team->removeActivity($activity));
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('default_audience_id')
            ->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function equipmentSets(): BelongsToMany
    {
        return $this->belongsToMany(EquipmentSet::class);
    }

    public function activityAudiences(): HasMany
    {
        return $this->hasMany(ActivityAudience::class);
    }

    public function audiences(): HasManyThrough
    {
        return $this->hasManyThrough(
            Audience::class,
            ActivityAudience::class,
            'activity_id', // Foreign key on the activity_audiences table...
            'id', // Foreign key on the audiences table...
            'id', // Local key on the activities table...
            'audience_id' // Local key on the activity_audiences table...
        );
    }
}
