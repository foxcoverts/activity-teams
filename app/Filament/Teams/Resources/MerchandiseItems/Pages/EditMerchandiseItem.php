<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Pages;

use App\Filament\Teams\Resources\MerchandiseItems\MerchandiseItemResource;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\Concerns\SyncsMerchandiseVariantOptions;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemVariant;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMerchandiseItem extends EditRecord
{
    use SyncsMerchandiseVariantOptions;

    protected static string $resource = MerchandiseItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var MerchandiseItem $record */
        $record = $this->getRecord();
        $record->loadMissing('variants');

        $data['stock_levels'] = $record->variants
            ->map(fn (MerchandiseItemVariant $variant): array => [
                'id' => $variant->getKey(),
                'label' => $variant->label,
                'availability' => $variant->availability?->value,
                'stock_quantity' => $variant->stock_quantity,
            ])
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['stock_levels']);

        return $data;
    }

    protected function beforeValidate(): void
    {
        $this->captureVariantOptionSelectionsForSync();
    }

    protected function afterSave(): void
    {
        $this->syncVariantOptionSelections();
        $this->syncStockLevels();
    }

    protected function syncStockLevels(): void
    {
        /** @var MerchandiseItem $record */
        $record = $this->getRecord();
        $levels = data_get($this->data, 'stock_levels');

        if (! is_array($levels)) {
            return;
        }

        foreach ($levels as $level) {
            if (! is_array($level) || blank($level['id'] ?? null)) {
                continue;
            }

            $quantity = max(0, (int) ($level['stock_quantity'] ?? 0));

            MerchandiseItemVariant::query()
                ->whereKey($level['id'])
                ->where('merchandise_item_id', $record->getKey())
                ->update(['stock_quantity' => $quantity]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
