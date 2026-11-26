<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\Schemas;

use App\Enums\MerchandiseOrderPaymentStatus;
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
                                MerchandiseOrderStatus::Approved => $record->isUnpaid()
                                    ? __('This order is approved. Payment is outstanding.')
                                    : ($record->isPaid() && (float) $record->amount_due <= 0
                                        ? __('This order is approved. There is nothing to pay.')
                                        : __('This order is approved.')),
                                MerchandiseOrderStatus::Rejected => __('This order was rejected. The member can reopen it into their basket, fix it, and submit again.'),
                                MerchandiseOrderStatus::Cancelled => __('This order was cancelled and will not be fulfilled.'),
                            }),
                        TextEntry::make('payment_status')
                            ->label(__('Payment'))
                            ->badge()
                            ->state(fn (MerchandiseOrder $record): MerchandiseOrderPaymentStatus => $record->paymentStatus())
                            ->visible(fn (MerchandiseOrder $record): bool => $record->paymentStatus() !== MerchandiseOrderPaymentStatus::None),
                        TextEntry::make('amount_due')
                            ->label(__('Amount due'))
                            ->formatStateUsing(fn (mixed $state): string => Number::currency((float) $state, 'GBP', 'en_GB'))
                            ->visible(fn (MerchandiseOrder $record): bool => $record->status === MerchandiseOrderStatus::Approved),
                        TextEntry::make('submitted_at')
                            ->label(__('Submitted'))
                            ->dateTime('j M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('approved_at')
                            ->label(__('Approved'))
                            ->dateTime('j M Y H:i')
                            ->placeholder('-')
                            ->visible(fn (MerchandiseOrder $record): bool => $record->status === MerchandiseOrderStatus::Approved),
                        TextEntry::make('paid_at')
                            ->label(__('Paid'))
                            ->dateTime('j M Y H:i')
                            ->placeholder('-')
                            ->visible(fn (MerchandiseOrder $record): bool => $record->isPaid()),
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
