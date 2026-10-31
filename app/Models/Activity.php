<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'name',
        'description',
    ];

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
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function qualificationRequirements(): HasMany
    {
        return $this->hasMany(ActivityQualificationRequirement::class)->orderBy('sort');
    }

    public function requiresQualification(): bool
    {
        return $this->qualificationRequirements()->exists();
    }

    public function metBy(User $user, Booking|CarbonInterface|null $asOf = null): bool
    {
        $requirements = $this->relationLoaded('qualificationRequirements')
            ? $this->qualificationRequirements
            : $this->qualificationRequirements()->get();

        if ($requirements->isEmpty()) {
            return true;
        }

        $date = $asOf instanceof Booking ? $asOf->qualificationAsOf() : $asOf;
        $qualifications = $user->relationLoaded('qualifications')
            ? $user->qualifications
            : $user->qualifications()->get();

        foreach ($requirements as $requirement) {
            foreach ($qualifications as $qualification) {
                if ($requirement->matches($qualification, $date)) {
                    return true;
                }
            }
        }

        return false;
    }
}
