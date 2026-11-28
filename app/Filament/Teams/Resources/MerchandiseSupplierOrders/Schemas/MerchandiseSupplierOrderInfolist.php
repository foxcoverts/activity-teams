<?php

namespace App\Filament\Teams\Resources\MerchandiseSupplierOrders\Schemas;

use App\Models\MerchandiseSupplierOrder;
use App\Support\MerchandiseSupplierOrderExporter;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MerchandiseSupplierOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Supplier order'))
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('supplier.name')
                            ->label(__('Supplier')),
                        TextEntry::make('sent_at')
                            ->label(__('Sent'))
                            ->dateTime('j M Y H:i')
                            ->placeholder(__('Draft')),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('-')
                            ->prose()
                            ->visible(fn (MerchandiseSupplierOrder $record): bool => filled($record->notes)),
                    ]),
                Section::make(__('Lines'))
                    ->icon(Heroicon::OutlinedTableCells)
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'flush-table-panel',
                    ])
                    ->schema([
                        ViewEntry::make('export_table')
                            ->hiddenLabel()
                            ->view('filament.teams.resources.merchandise-supplier-orders.export-table')
                            ->viewData(function (MerchandiseSupplierOrder $record): array {
                                $record->loadMissing([
                                    'lines.order.user',
                                    'lines.variant.optionValues.axis',
                                    'lines.item.optionAxes',
                                ]);

                                $arrivedIds = $record->lines
                                    ->filter(fn ($line): bool => $line->isArrived())
                                    ->modelKeys();

                                return [
                                    'table' => app(MerchandiseSupplierOrderExporter::class)->board(
                                        lines: $record->lines,
                                        selectedLineIds: $arrivedIds,
                                        editable: false,
                                        showOnHandHint: false,
                                        showArrivedColumn: true,
                                    ),
                                ];
                            }),
                    ]),
            ]);
    }
}
