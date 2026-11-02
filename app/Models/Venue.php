<?php

namespace App\Models;

use App\Support\Coords;
use App\Support\Location;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        static::addGlobalScope('catalog', function (Builder $query): void {
            $query->whereNull($query->getModel()->qualifyColumn('booking_id'));
        });

        static::deleting(function (Venue $venue): void {
            $venue->teams()
                ->where('teams.default_venue_id', $venue->getKey())
                ->get()
                ->each(fn (Team $team) => $team->removeVenue($venue));
        });

        static::saving(function (Venue $venue): void {
            if (filled($venue->booking_id) || blank($venue->name)) {
                return;
            }

            $venue->name = trim($venue->name);
            $existingVenue = static::findCatalogNameConflict(
                $venue->name,
                $venue->postcode,
                $venue->exists ? (string) $venue->getKey() : null,
            );

            if ($existingVenue instanceof Venue) {
                throw ValidationException::withMessages(
                    static::catalogConflictMessages($existingVenue, $venue->postcode),
                );
            }
        });
    }

    /**
     * Same catalog name conflicts unless the new venue has a postcode that the
     * existing namesake lacks, or both have different postcodes.
     */
    public static function findCatalogNameConflict(
        string $name,
        ?string $postcode = null,
        ?string $excludeId = null,
    ): ?Venue {
        if (blank($name)) {
            return null;
        }

        $newPostcodeKey = static::postcodeKey((string) $postcode);

        return static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($excludeId !== null, fn (Builder $query) => $query->where('venues.id', '!=', $excludeId))
            ->orderBy('name')
            ->get()
            ->first(fn (Venue $existing): bool => static::catalogIdentityConflicts(
                $newPostcodeKey,
                static::postcodeKey((string) $existing->postcode),
            ));
    }

    /**
     * @return array<string, string>
     */
    public static function catalogConflictMessages(Venue $existing, ?string $newPostcode): array
    {
        if (static::postcodeKey((string) $newPostcode) === '') {
            $place = $existing->optionPlaceHint();

            return [
                'postcode' => $place !== null
                    ? __('Add a postcode. ":name" already exists (:place).', [
                        'name' => $existing->name,
                        'place' => $place,
                    ])
                    : __('Add a postcode. ":name" already exists without a postcode.', [
                        'name' => $existing->name,
                    ]),
            ];
        }

        return [
            'postcode' => __('A catalog venue named :name already exists at :postcode.', [
                'name' => $existing->name,
                'postcode' => trim((string) $existing->postcode),
            ]),
        ];
    }

    public static function catalogIdentityConflicts(string $newPostcodeKey, string $existingPostcodeKey): bool
    {
        if ($newPostcodeKey === '') {
            return true;
        }

        if ($existingPostcodeKey === '') {
            return false;
        }

        return $newPostcodeKey === $existingPostcodeKey;
    }

    public function optionLabel(): string
    {
        $place = $this->optionPlaceHint();

        return $place === null
            ? $this->name
            : "{$this->name} ({$place})";
    }

    public function optionPlaceHint(): ?string
    {
        $city = filled($this->city) ? trim((string) $this->city) : null;
        $region = filled($this->region) ? trim((string) $this->region) : null;
        $postcode = filled($this->postcode) ? trim((string) $this->postcode) : null;
        $street = filled($this->street) ? trim((string) $this->street) : null;

        return match (true) {
            $city !== null && $postcode !== null => "{$city}, {$postcode}",
            $region !== null && $postcode !== null => "{$region}, {$postcode}",
            $postcode !== null => $postcode,
            $city !== null => $city,
            $region !== null => $region,
            $street !== null => $street,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function selectOptions(?Builder $query = null): array
    {
        return ($query ?? static::query())
            ->orderBy('name')
            ->get(['id', 'name', 'street', 'city', 'region', 'postcode'])
            ->mapWithKeys(fn (Venue $venue): array => [
                (string) $venue->getKey() => $venue->optionLabel(),
            ])
            ->all();
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

    public function scopeWithPrivate(Builder $query): void
    {
        $query->withoutGlobalScope('catalog');
    }

    public function scopeOnlyPrivate(Builder $query): void
    {
        $query
            ->withoutGlobalScope('catalog')
            ->whereNotNull($query->getModel()->qualifyColumn('booking_id'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public static function similarCatalogOptions(array $attributes, ?string $excludeId = null): array
    {
        $nameKey = static::nameKey((string) ($attributes['name'] ?? ''));
        $streetKey = static::nameKey((string) ($attributes['street'] ?? ''));
        $cityKey = static::nameKey((string) ($attributes['city'] ?? ''));
        $postcodeKey = static::postcodeKey((string) ($attributes['postcode'] ?? ''));
        $coords = Coords::tryFrom(
            is_numeric($attributes['latitude'] ?? null) ? (float) $attributes['latitude'] : null,
            is_numeric($attributes['longitude'] ?? null) ? (float) $attributes['longitude'] : null,
        );

        if ($nameKey === '' && $streetKey === '' && $postcodeKey === '' && $coords === null) {
            return [];
        }

        return static::query()
            ->select(['id', 'name', 'street', 'city', 'region', 'postcode', 'latitude', 'longitude'])
            ->when($excludeId !== null, fn (Builder $query) => $query->where('venues.id', '!=', $excludeId))
            ->orderBy('name')
            ->get()
            ->map(function (Venue $venue) use ($nameKey, $streetKey, $cityKey, $postcodeKey, $coords): ?array {
                $venueNameKey = static::nameKey($venue->name);
                $venueStreetKey = static::nameKey((string) $venue->street);
                $venueCityKey = static::nameKey((string) $venue->city);
                $venuePostcodeKey = static::postcodeKey((string) $venue->postcode);
                $venueCoords = Coords::tryFrom(
                    is_numeric($venue->latitude) ? (float) $venue->latitude : null,
                    is_numeric($venue->longitude) ? (float) $venue->longitude : null,
                );

                $samePostcode = $postcodeKey !== ''
                    && $venuePostcodeKey !== ''
                    && $postcodeKey === $venuePostcodeKey;
                $similarName = static::hasSimilarName($nameKey, $venueNameKey);
                $sameStreet = $streetKey !== ''
                    && $venueStreetKey !== ''
                    && $streetKey === $venueStreetKey
                    && ($samePostcode || ($cityKey !== '' && $cityKey === $venueCityKey));
                $nearbyPin = $coords !== null
                    && $venueCoords !== null
                    && $coords->distanceTo($venueCoords) <= 150;

                $reason = match (true) {
                    $samePostcode => __('Same postcode'),
                    $nearbyPin => __('Nearby pin'),
                    $sameStreet => __('Same street'),
                    $similarName => __('Similar name'),
                    default => null,
                };

                if ($reason === null) {
                    return null;
                }

                return [
                    'id' => (string) $venue->getKey(),
                    'label' => $venue->optionLabel(),
                    'name' => $venue->name,
                    'reason' => $reason,
                    'rank' => match (true) {
                        $samePostcode => 0,
                        $nearbyPin => 1,
                        $sameStreet => 2,
                        default => 3,
                    },
                ];
            })
            ->filter()
            ->sortBy([['rank', 'asc'], ['name', 'asc']])
            ->take(5)
            ->mapWithKeys(fn (array $venue): array => [
                $venue['id'] => "{$venue['label']} — {$venue['reason']}",
            ])
            ->all();
    }

    private static function hasSimilarName(string $nameKey, string $venueNameKey): bool
    {
        if ($nameKey === '' || $venueNameKey === '') {
            return false;
        }

        if ($nameKey === $venueNameKey) {
            return true;
        }

        if (min(strlen($nameKey), strlen($venueNameKey)) < 6) {
            return false;
        }

        similar_text($nameKey, $venueNameKey, $similarity);

        return $similarity >= 80
            || (abs(strlen($nameKey) - strlen($venueNameKey)) <= 2
                && levenshtein($nameKey, $venueNameKey) <= 2);
    }

    private static function nameKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value))) ?? '';
    }

    private static function postcodeKey(string $value): string
    {
        return preg_replace('/\s+/', '', Str::upper($value)) ?? '';
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
