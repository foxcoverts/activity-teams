<?php

namespace App\Models;

use App\Support\Coords;
use App\Support\Location;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'name',
        'notes',
        'street',
        'street2',
        'city',
        'region',
        'postcode',
        'country',
        'latitude',
        'longitude',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override]
    protected function casts()
    {
        return parent::casts() + [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Venue $venue): void {
            $venue->teams()
                ->where('teams.default_venue_id', $venue->getKey())
                ->get()
                ->each(fn (Team $team) => $team->removeVenue($venue));
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

    protected function location(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => new Location(
                name: $attributes['name'] ?? '',
                street: $attributes['street'] ?? null,
                street2: $attributes['street2'] ?? null,
                city: $attributes['city'] ?? null,
                region: $attributes['region'] ?? null,
                postcode: $attributes['postcode'] ?? null,
                country: $attributes['country'] ?? null,
            ),
        );
    }

    protected function coords(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => Coords::tryFrom(
                $attributes['latitude'] ?? null,
                $attributes['longitude'] ?? null,
            ),
            set: fn (mixed $value, array $attributes) => [
                'latitude' => $value?->latitude,
                'longitude' => $value?->longitude,
            ],
        );
    }
}
