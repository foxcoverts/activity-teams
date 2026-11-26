<?php

namespace App\Models;

use App\Enums\MerchandiseAvailability;
use App\Support\MerchandisePersonalisation;
use Database\Factories\MerchandiseItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MerchandiseItem extends Model
{
    /** @use HasFactory<MerchandiseItemFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'team_id',
        'title',
        'description',
        'personalisation_fields',
        'availability',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MerchandiseItemVariant::class)->orderBy('sort')->orderBy('label');
    }

    public function optionAxes(): HasMany
    {
        return $this->hasMany(MerchandiseItemOptionAxis::class)->orderBy('sort')->orderBy('name');
    }

    public function images(): HasMany
    {
        return $this->hasMany(MerchandiseItemImage::class)->orderBy('sort');
    }

    public function hasOptionAxes(): bool
    {
        if ($this->relationLoaded('optionAxes')) {
            return $this->optionAxes->isNotEmpty();
        }

        return $this->optionAxes()->exists();
    }

    /**
     * @return list<array{key: string, label: string, required: bool}>
     */
    public function personalisationFieldDefinitions(): array
    {
        return MerchandisePersonalisation::normalizeFields(
            is_array($this->personalisation_fields) ? $this->personalisation_fields : [],
        );
    }

    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->whereBelongsTo($team);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('availability', MerchandiseAvailability::Available);
    }

    public function thumbnailImage(): ?MerchandiseItemImage
    {
        if ($this->relationLoaded('images')) {
            return $this->images->sortBy('sort')->first();
        }

        return $this->images()->orderBy('sort')->first();
    }

    /**
     * Lowest price among available variants, for Shop “from £X” display.
     */
    public function lowestAvailablePrice(): ?string
    {
        /** @var Collection<int, MerchandiseItemVariant> $variants */
        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get();

        $prices = $variants
            ->filter(fn (MerchandiseItemVariant $variant): bool => $variant->availability === MerchandiseAvailability::Available)
            ->pluck('price')
            ->filter(fn (mixed $price): bool => $price !== null);

        if ($prices->isEmpty()) {
            return null;
        }

        return (string) $prices->map(fn (mixed $price): float => (float) $price)->min();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'availability' => MerchandiseAvailability::class,
            'personalisation_fields' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MerchandiseItem $item): void {
            $item->personalisation_fields = MerchandisePersonalisation::normalizeFields(
                is_array($item->personalisation_fields) ? $item->personalisation_fields : [],
            );
        });
    }
}
