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
                Section::make(__('Export'))
                    ->icon(Heroicon::OutlinedTableCells)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('export_table')
                            ->hiddenLabel()
                            ->view('filament.teams.resources.merchandise-supplier-orders.export-table')
                            ->viewData(fn (MerchandiseSupplierOrder $record): array => [
                                'table' => app(MerchandiseSupplierOrderExporter::class)->table($record),
                            ]),
                    ]),
            ]);
    }
}
