<?php

namespace App\Models;

use App\Enums\MerchandiseAvailability;
use Database\Factories\MerchandiseItemVariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MerchandiseItemVariant extends Model
{
    /** @use HasFactory<MerchandiseItemVariantFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'merchandise_item_id',
        'label',
        'price',
        'availability',
        'sort',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MerchandiseItemVariant $variant): void {
            if ($variant->issuedMerchandise()->exists()) {
                throw ValidationException::withMessages([
                    'variants' => __('This variant cannot be deleted because it appears on the issued log. Mothball it instead.'),
                ]);
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItem::class, 'merchandise_item_id');
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            MerchandiseItemOptionValue::class,
            'merchandise_item_variant_options',
        )->using(MerchandiseItemVariantOption::class)->withTimestamps();
    }

    public function issuedMerchandise(): HasMany
    {
        return $this->hasMany(IssuedMerchandise::class);
    }

    /**
     * Build a display label from option values in axis order.
     *
     * @param  Collection<int, MerchandiseItemOptionValue>|list<MerchandiseItemOptionValue>  $values
     * @param  Collection<int, MerchandiseItemOptionAxis>|list<MerchandiseItemOptionAxis>|null  $axes
     */
    public static function composeLabelFromOptionValues(Collection|array $values, Collection|array|null $axes = null): string
    {
        $valueCollection = collect(
            $values instanceof Collection ? $values->all() : $values,
        )->values();

        if ($valueCollection->isEmpty()) {
            return '';
        }

        $valueCollection->each(function (mixed $value): void {
            if ($value instanceof MerchandiseItemOptionValue) {
                $value->loadMissing('axis');
            }
        });

        if ($axes !== null) {
            $axisOrder = collect($axes instanceof Collection ? $axes->all() : $axes)
                ->sortBy(fn (MerchandiseItemOptionAxis $axis): int => $axis->sort)
                ->values()
                ->mapWithKeys(fn (MerchandiseItemOptionAxis $axis, int $index): array => [(string) $axis->getKey() => $index]);

            $valueCollection = $valueCollection->sortBy(
                fn (MerchandiseItemOptionValue $value): int => $axisOrder->get((string) $value->merchandise_item_option_axis_id, PHP_INT_MAX),
            )->values();
        } else {
            $valueCollection = $valueCollection
                ->sortBy(fn (MerchandiseItemOptionValue $value): array => [
                    $value->axis?->sort ?? PHP_INT_MAX,
                    $value->sort,
                ])
                ->values();
        }

        return $valueCollection
            ->map(fn (MerchandiseItemOptionValue $value): string => $value->label)
            ->filter()
            ->implode(' · ');
    }

    public function syncComposedLabel(?Collection $axes = null): void
    {
        $this->loadMissing('optionValues.axis');

        $label = static::composeLabelFromOptionValues($this->optionValues, $axes ?? $this->item?->optionAxes);

        if ($label !== '') {
            $this->label = $label;
        }
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('availability', MerchandiseAvailability::Available);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'availability' => MerchandiseAvailability::class,
            'sort' => 'integer',
        ];
    }
}
