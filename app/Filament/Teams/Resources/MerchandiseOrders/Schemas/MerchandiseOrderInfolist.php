<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\Schemas;

use App\Enums\MerchandiseOrderStatus;
use App\Filament\Support\Infolists\PersonEntry;
use App\Models\MerchandiseOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class MerchandiseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Order'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->helperText(fn (MerchandiseOrder $record): ?string => match ($record->status) {
                                MerchandiseOrderStatus::Submitted => __('Waiting for a team manager to review this order.'),
                                MerchandiseOrderStatus::Cancelled => __('This order was cancelled and will not be fulfilled.'),
                                default => null,
                            }),
                        TextEntry::make('submitted_at')
                            ->label(__('Submitted'))
                            ->dateTime('j M Y H:i')
                            ->placeholder('-'),
                        PersonEntry::make('user.name')
                            ->label(__('Member'))
                            ->memberUrl(),
                        TextEntry::make('total')
                            ->label(__('Total'))
                            ->state(fn (MerchandiseOrder $record): string => Number::currency($record->total(), 'GBP', 'en_GB')),
                    ]),
            ]);
    }
}
