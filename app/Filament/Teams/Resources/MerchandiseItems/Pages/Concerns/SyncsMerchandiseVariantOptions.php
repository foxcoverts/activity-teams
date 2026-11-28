<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Pages\Concerns;

use App\Actions\SyncMerchandiseVariantOptionSelectionsAction;
use App\Models\MerchandiseItem;

trait SyncsMerchandiseVariantOptions
{
    /**
     * @var array{optionAxes: array<string, mixed>, variants: array<string, mixed>}
     */
    protected array $pendingVariantOptionSync = [
        'optionAxes' => [],
        'variants' => [],
    ];

    protected function captureVariantOptionSelectionsForSync(): void
    {
        $this->pendingVariantOptionSync = [
            'optionAxes' => is_array(data_get($this->data, 'optionAxes'))
                ? data_get($this->data, 'optionAxes')
                : [],
            'variants' => is_array(data_get($this->data, 'variants'))
                ? data_get($this->data, 'variants')
                : [],
        ];
    }

    protected function syncVariantOptionSelections(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof MerchandiseItem) {
            return;
        }

        $variantsState = $this->variantsStateWithResolvedIds(
            $record->fresh(['variants']),
            $this->pendingVariantOptionSync['variants'],
        );

        app(SyncMerchandiseVariantOptionSelectionsAction::class)(
            $record->fresh(['optionAxes.values', 'variants']),
            $this->pendingVariantOptionSync['optionAxes'],
            $variantsState,
        );
    }

    /**
     * @param  array<string, mixed>  $variantsState
     * @return array<string, mixed>
     */
    private function variantsStateWithResolvedIds(MerchandiseItem $item, array $variantsState): array
    {
        $dbVariants = $item->variants->sortBy('sort')->values();
        $index = 0;

        foreach ($variantsState as $key => $row) {
            if (! is_array($row)) {
                $index++;

                continue;
            }

            $stateId = $row['id'] ?? null;
            $stillExists = filled($stateId)
                && $dbVariants->contains(fn ($variant): bool => (string) $variant->getKey() === (string) $stateId);

            if (! $stillExists && isset($dbVariants[$index])) {
                $variantsState[$key]['id'] = $dbVariants[$index]->getKey();
            }

            $index++;
        }

        return $variantsState;
    }
}
