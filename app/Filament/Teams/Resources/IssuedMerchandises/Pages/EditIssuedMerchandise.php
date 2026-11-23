<?php

namespace App\Filament\Teams\Resources\IssuedMerchandises\Pages;

use App\Filament\Teams\Resources\IssuedMerchandises\IssuedMerchandiseResource;
use App\Models\IssuedMerchandise;
use App\Models\MerchandiseItemVariant;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditIssuedMerchandise extends EditRecord
{
    protected static string $resource = IssuedMerchandiseResource::class;

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
        $variantId = $data['merchandise_item_variant_id'] ?? null;

        if (filled($variantId)) {
            $data['merchandise_item_id'] = MerchandiseItemVariant::query()
                ->whereKey($variantId)
                ->value('merchandise_item_id');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof IssuedMerchandise) {
            $data['source'] = $record->source;
            $data['team_id'] = $record->team_id;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
