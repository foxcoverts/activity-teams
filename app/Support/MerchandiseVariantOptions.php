<?php

namespace App\Support;

use App\Enums\MerchandiseAvailability;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemOptionAxis;
use App\Models\MerchandiseItemOptionValue;
use App\Models\MerchandiseItemVariant;
use Illuminate\Support\Collection;

final class MerchandiseVariantOptions
{
    public const MaxAxes = 3;

    /**
     * @param  array<string, string>  $selectedValueIdsByAxisId
     */
    public static function resolveVariant(MerchandiseItem $item, array $selectedValueIdsByAxisId): ?MerchandiseItemVariant
    {
        $item->loadMissing(['optionAxes.values', 'variants.optionValues']);

        $axes = $item->optionAxes;

        if ($axes->isEmpty()) {
            return null;
        }

        $requiredValueIds = $axes
            ->map(fn (MerchandiseItemOptionAxis $axis): ?string => $selectedValueIdsByAxisId[(string) $axis->getKey()] ?? null)
            ->filter(fn (?string $id): bool => filled($id))
            ->values();

        if ($requiredValueIds->count() !== $axes->count()) {
            return null;
        }

        return $item->variants
            ->first(function (MerchandiseItemVariant $variant) use ($requiredValueIds): bool {
                $variantValueIds = $variant->optionValues
                    ->map(fn (MerchandiseItemOptionValue $value): string => (string) $value->getKey())
                    ->sort()
                    ->values();

                return $variantValueIds->all() === $requiredValueIds->sort()->values()->all();
            });
    }

    /**
     * @param  array<string, string>  $selectedValueIdsByAxisId
     * @return Collection<int, MerchandiseItemOptionValue>
     */
    public static function availableValuesForAxis(
        MerchandiseItem $item,
        MerchandiseItemOptionAxis $axis,
        array $selectedValueIdsByAxisId = [],
        bool $availableOnly = true,
    ): Collection {
        $item->loadMissing(['optionAxes', 'variants.optionValues']);

        $partial = collect($selectedValueIdsByAxisId)
            ->reject(fn (string $valueId, string $axisId): bool => (string) $axisId === (string) $axis->getKey())
            ->filter(fn (string $valueId): bool => filled($valueId));

        $matchingVariants = $item->variants
            ->when(
                $availableOnly,
                fn (Collection $variants): Collection => $variants->filter(
                    fn (MerchandiseItemVariant $variant): bool => $variant->availability === MerchandiseAvailability::Available,
                ),
            )
            ->filter(function (MerchandiseItemVariant $variant) use ($partial): bool {
                foreach ($partial as $axisId => $valueId) {
                    $hasValue = $variant->optionValues->contains(
                        fn (MerchandiseItemOptionValue $value): bool => (string) $value->getKey() === (string) $valueId
                            && (string) $value->merchandise_item_option_axis_id === (string) $axisId,
                    );

                    if (! $hasValue) {
                        return false;
                    }
                }

                return true;
            });

        return $matchingVariants
            ->flatMap(fn (MerchandiseItemVariant $variant): Collection => $variant->optionValues)
            ->filter(fn (MerchandiseItemOptionValue $value): bool => (string) $value->merchandise_item_option_axis_id === (string) $axis->getKey())
            ->unique(fn (MerchandiseItemOptionValue $value): string => (string) $value->getKey())
            ->sortBy(fn (MerchandiseItemOptionValue $value): array => [$value->sort, $value->label])
            ->values();
    }

    /**
     * Cartesian product of value IDs across axes (in axis order).
     *
     * @param  Collection<int, MerchandiseItemOptionAxis>  $axes
     * @return list<list<string>>
     */
    public static function combinationValueIdSets(Collection $axes): array
    {
        $axes = $axes->values();

        if ($axes->isEmpty()) {
            return [];
        }

        $sets = [[]];

        foreach ($axes as $axis) {
            /** @var Collection<int, MerchandiseItemOptionValue> $values */
            $values = $axis->relationLoaded('values') ? $axis->values : $axis->values()->get();
            $next = [];

            foreach ($sets as $prefix) {
                foreach ($values as $value) {
                    $next[] = [...$prefix, (string) $value->getKey()];
                }
            }

            $sets = $next;
        }

        return $sets;
    }

    /**
     * @param  list<string>  $valueIds
     */
    public static function combinationKey(array $valueIds): string
    {
        $sorted = $valueIds;
        sort($sorted);

        return implode('|', $sorted);
    }
}
