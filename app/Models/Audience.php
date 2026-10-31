<?php

namespace App\Models;

use Database\Factories\AudienceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Audience extends Model
{
    /** @use HasFactory<AudienceFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'name',
        'description',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Audience $audience): void {
            if ($audience->bookings()->exists()) {
                throw ValidationException::withMessages([
                    'name' => __('This audience is used by a booking.'),
                ]);
            }

            $audience->activityAudiences()->get()->each(function (ActivityAudience $pairing): void {
                $pairing->delete();
            });
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function activityAudiences(): HasMany
    {
        return $this->hasMany(ActivityAudience::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
