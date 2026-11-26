<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\Schemas;

use App\Enums\MerchandiseOrderStatus;
use App\Filament\Support\Infolists\PersonEntry;
use App\Models\MerchandiseOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                                MerchandiseOrderStatus::Approved => __('This order is approved. An invoice will follow.'),
                                MerchandiseOrderStatus::Rejected => __('This order was rejected. The member can reopen it into their basket, fix it, and submit again.'),
                                MerchandiseOrderStatus::Cancelled => __('This order was cancelled and will not be fulfilled.'),
                            }),
                        TextEntry::make('submitted_at')
                            ->label(__('Submitted'))
                            ->dateTime('j M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('approved_at')
                            ->label(__('Approved'))
                            ->dateTime('j M Y H:i')
                            ->placeholder('-')
                            ->visible(fn (MerchandiseOrder $record): bool => $record->status === MerchandiseOrderStatus::Approved),
                        TextEntry::make('rejected_at')
                            ->label(__('Rejected'))
                            ->dateTime('j M Y H:i')
                            ->placeholder('-')
                            ->visible(fn (MerchandiseOrder $record): bool => $record->status === MerchandiseOrderStatus::Rejected),
                        TextEntry::make('rejection_reason')
                            ->label(__('Rejection reason'))
                            ->placeholder('-')
                            ->visible(fn (MerchandiseOrder $record): bool => $record->status === MerchandiseOrderStatus::Rejected),
                        TextEntry::make('approval_note')
                            ->label(__('Approval note'))
                            ->placeholder('-')
                            ->visible(fn (MerchandiseOrder $record): bool => filled($record->approval_note)),
                        PersonEntry::make('user.name')
                            ->label(__('Member'))
                            ->memberUrl(),
                    ]),
            ]);
    }
}
