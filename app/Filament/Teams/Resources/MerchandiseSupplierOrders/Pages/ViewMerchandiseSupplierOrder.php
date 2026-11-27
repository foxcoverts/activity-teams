<?php

namespace App\Filament\Teams\Resources\MerchandiseSupplierOrders\Pages;

use App\Filament\Teams\Resources\MerchandiseSupplierOrders\MerchandiseSupplierOrderResource;
use App\Models\MerchandiseSupplierOrder;
use App\Support\MerchandiseSupplierOrderExporter;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewMerchandiseSupplierOrder extends ViewRecord
{
    protected static string $resource = MerchandiseSupplierOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadCsv')
                ->label(__('Download CSV'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn (): bool => $this->getRecord()->lines()->exists())
                ->action(function (): StreamedResponse {
                    /** @var MerchandiseSupplierOrder $record */
                    $record = $this->getRecord();
                    $exporter = app(MerchandiseSupplierOrderExporter::class);
                    $csv = $exporter->toCsv($record);

                    return response()->streamDownload(
                        function () use ($csv): void {
                            echo $csv;
                        },
                        $exporter->csvFilename($record),
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    );
                }),
            Action::make('backToBoard')
                ->label(__('Back to board'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(fn (): string => MerchandiseSupplierOrderResource::getUrl('index', [
                    'supplierId' => $this->getRecord()->merchandise_supplier_id,
                    'orderKey' => 'draft',
                ])),
        ];
    }
}
