<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ActivityAudienceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ActivityAudience extends Model
{
    /** @use HasFactory<ActivityAudienceFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'activity_id',
        'audience_id',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ActivityAudience $pairing): void {
            if ($pairing->isUsedByBookings()) {
                throw ValidationException::withMessages([
                    'audience_id' => __('This audience is used by a booking.'),
                ]);
            }

            DB::table('activity_team')
                ->where('activity_id', $pairing->activity_id)
                ->where('default_audience_id', $pairing->audience_id)
                ->update(['default_audience_id' => null]);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function audience(): BelongsTo
    {
        return $this->belongsTo(Audience::class);
    }

    public function qualificationRequirements(): HasMany
    {
        return $this->hasMany(ActivityQualificationRequirement::class)->orderBy('sort');
    }

    public function isUsedByBookings(): bool
    {
        return Booking::query()
            ->where('activity_id', $this->activity_id)
            ->where('audience_id', $this->audience_id)
            ->exists();
    }

    public function requiresQualification(): bool
    {
        if ($this->relationLoaded('qualificationRequirements')) {
            return $this->qualificationRequirements->isNotEmpty();
        }

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
