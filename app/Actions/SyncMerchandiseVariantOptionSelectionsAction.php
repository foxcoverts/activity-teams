<?php

namespace App\Actions;

use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemOptionAxis;
use App\Models\MerchandiseItemOptionValue;
use App\Models\MerchandiseItemVariant;
use App\Support\MerchandiseVariantOptions;
use Illuminate\Support\Collection;

class SyncMerchandiseVariantOptionSelectionsAction
{
    /**
     * @param  array<string, mixed>  $optionAxesState
     * @param  array<string, mixed>  $variantsState
     */
    public function __invoke(MerchandiseItem $item, array $optionAxesState, array $variantsState): void
    {
        $item->load(['optionAxes.values', 'variants']);

        if ($item->optionAxes->isEmpty()) {
            foreach ($item->variants as $variant) {
                $variant->optionValues()->sync([]);
            }

            return;
        }

        /** @var Collection<string, MerchandiseItemOptionValue> $dbValuesByAxisAndLabel */
        $dbValuesByAxisAndLabel = $item->optionAxes
            ->flatMap(function (MerchandiseItemOptionAxis $axis): Collection {
                return $axis->values->mapWithKeys(
                    fn (MerchandiseItemOptionValue $value): array => [
                        $this->axisValueKey($axis->name, $value->label) => $value,
                    ],
                );
            });

        $dbValueIds = $item->optionAxes
            ->flatMap(fn (MerchandiseItemOptionAxis $axis): Collection => $axis->values)
            ->mapWithKeys(fn (MerchandiseItemOptionValue $value): array => [
                (string) $value->getKey() => $value,
            ]);

        foreach ($variantsState as $variantState) {
            if (! is_array($variantState)) {
                continue;
            }

            $variantId = $variantState['id'] ?? null;

            if (! filled($variantId)) {
                continue;
            }

            $variant = $item->variants->firstWhere('id', $variantId);

            if (! $variant instanceof MerchandiseItemVariant) {
                continue;
            }

            $resolvedValueIds = collect($variantState['option_value_ids'] ?? [])
                ->filter(fn (mixed $value): bool => filled($value))
                ->map(function (mixed $selectedKey, mixed $axisKey) use ($optionAxesState, $dbValuesByAxisAndLabel, $dbValueIds): ?string {
                    $selectedKey = (string) $selectedKey;

                    if ($dbValueIds->has($selectedKey)) {
                        return $selectedKey;
                    }

                    $axisState = $this->findAxisState($optionAxesState, (string) $axisKey);

                    if ($axisState === null) {
                        return null;
                    }

                    $valueState = $this->findValueState($axisState, $selectedKey);

                    if ($valueState === null) {
                        return null;
                    }

                    $match = $dbValuesByAxisAndLabel->get(
                        $this->axisValueKey((string) ($axisState['name'] ?? ''), (string) ($valueState['label'] ?? '')),
                    );

                    return $match instanceof MerchandiseItemOptionValue
                        ? (string) $match->getKey()
                        : null;
                })
                ->filter()
                ->unique()
                ->values()
                ->all();

            $variant->optionValues()->sync($resolvedValueIds);
            $variant->unsetRelation('optionValues');
            $variant->load('optionValues.axis');
            $variant->syncComposedLabel($item->optionAxes);
            $variant->save();
        }
    }

    /**
     * @param  array<string, mixed>  $optionAxesState
     * @return array<string, mixed>|null
     */
    private function findAxisState(array $optionAxesState, string $axisKey): ?array
    {
        if (isset($optionAxesState[$axisKey]) && is_array($optionAxesState[$axisKey])) {
            return $optionAxesState[$axisKey];
        }

        foreach ($optionAxesState as $axisState) {
            if (! is_array($axisState)) {
                continue;
            }

            if ((string) ($axisState['id'] ?? '') === $axisKey) {
                return $axisState;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $axisState
     * @return array<string, mixed>|null
     */
    private function findValueState(array $axisState, string $valueKey): ?array
    {
        $values = $axisState['values'] ?? [];

        if (isset($values[$valueKey]) && is_array($values[$valueKey])) {
            return $values[$valueKey];
        }

        foreach ($values as $valueFormKey => $valueState) {
            if (! is_array($valueState)) {
                continue;
            }

            if ((string) $valueFormKey === $valueKey || (string) ($valueState['id'] ?? '') === $valueKey) {
                return $valueState;
            }
        }

        return null;
    }

    private function axisValueKey(string $axisName, string $label): string
    {
        return mb_strtolower(trim($axisName))."\0".mb_strtolower(trim($label));
    }

    /**
     * Append missing cartesian SKUs into form variant state.
     *
     * @param  array<string, mixed>  $optionAxesState
     * @param  array<string, mixed>  $variantsState
     * @return array<string, mixed>
     */
    public static function appendMissingCombinationsToVariantState(array $optionAxesState, array $variantsState): array
    {
        $axes = collect($optionAxesState)
            ->filter(fn (mixed $axis): bool => is_array($axis) && filled($axis['name'] ?? null));

        if ($axes->isEmpty()) {
            return $variantsState;
        }

        if ($axes->count() > MerchandiseVariantOptions::MaxAxes) {
            return $variantsState;
        }

        $valueSets = [[]];

        foreach ($axes as $axisFormKey => $axis) {
            $values = collect($axis['values'] ?? [])
                ->filter(fn (mixed $value): bool => is_array($value) && filled($value['label'] ?? null));

            if ($values->isEmpty()) {
                return $variantsState;
            }

            $axisSelectKey = (string) ($axis['id'] ?? $axisFormKey);
            $next = [];

            foreach ($valueSets as $prefix) {
                foreach ($values as $valueFormKey => $value) {
                    $valueSelectKey = (string) ($value['id'] ?? $valueFormKey);
                    $next[] = [...$prefix, $axisSelectKey => $valueSelectKey];
                }
            }

            $valueSets = $next;
        }

        $existingKeys = collect($variantsState)
            ->map(function (mixed $variant): ?string {
                if (! is_array($variant)) {
                    return null;
                }

                $ids = collect($variant['option_value_ids'] ?? [])
                    ->filter()
                    ->map(fn (mixed $id): string => (string) $id)
                    ->sort()
                    ->values()
                    ->all();

                return $ids === [] ? null : MerchandiseVariantOptions::combinationKey($ids);
            })
            ->filter()
            ->all();

        foreach ($valueSets as $selection) {
            $valueIds = array_values($selection);
            $key = MerchandiseVariantOptions::combinationKey($valueIds);

            if (in_array($key, $existingKeys, true)) {
                continue;
            }

            $labels = [];

            foreach ($axes as $axisFormKey => $axis) {
                $axisSelectKey = (string) ($axis['id'] ?? $axisFormKey);
                $valueSelectKey = $selection[$axisSelectKey] ?? null;

                if ($valueSelectKey === null) {
                    continue;
                }

                $valueState = collect($axis['values'] ?? [])
                    ->first(function (mixed $value, string|int $valueFormKey) use ($valueSelectKey): bool {
                        if (! is_array($value)) {
                            return false;
                        }

                        $key = (string) ($value['id'] ?? $valueFormKey);

                        return $key === (string) $valueSelectKey;
                    });

                if (is_array($valueState)) {
                    $labels[] = (string) ($valueState['label'] ?? '');
                }
            }

            $variantsState[uniqid('variant_')] = [
                'label' => collect($labels)->filter()->implode(' · '),
                'price' => '0.00',
                'availability' => 'draft',
                'option_value_ids' => $selection,
            ];

            $existingKeys[] = $key;
        }

        return $variantsState;
    }
}
