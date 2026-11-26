<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\RelationManagers;

use App\Actions\ApproveMerchandiseOrderAction;
use App\Actions\RejectMerchandiseOrderAction;
use App\Actions\SetMerchandiseOrderDiscountAction;
use App\Actions\SetMerchandiseOrderLinePriceAction;
use App\Filament\Support\SubmitsOnModEnter;
use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseOrderLine;
use App\Models\User;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Items';

    protected static ?string $modelLabel = 'item';

    protected static ?string $pluralModelLabel = 'items';

    protected static bool $shouldSkipAuthorization = true;

    protected static ?bool $isLazyLoaded = false;

    public function isReadOnly(): bool
    {
        return ! $this->canManageDiscounts();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('item.images')->orderBy('sort'))
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->square()
                    ->imageSize(48)
                    ->grow(false)
                    ->getStateUsing(fn (MerchandiseOrderLine $record): ?string => $record->item?->thumbnailImage()?->thumbnailUrl())
                    ->alt(fn (MerchandiseOrderLine $record): string => $record->item_title)
                    ->url(fn (MerchandiseOrderLine $record): ?string => static::productUrl($record)),
                TextColumn::make('item_title')
                    ->label(__('Item'))
                    ->url(fn (MerchandiseOrderLine $record): ?string => static::productUrl($record))
                    ->description(function (MerchandiseOrderLine $record): ?string {
                        $parts = collect($record->personalisationSnapshot())
                            ->map(fn (array $row): string => $row['label'].': '.$row['value']);

                        return $parts->isEmpty() ? null : $parts->implode(' · ');
                    }),
                TextColumn::make('variant_label')
                    ->label(__('Variant'))
                    ->placeholder('—'),
                TextColumn::make('unit_price')
                    ->label(__('Price'))
                    ->alignEnd()
                    ->html()
                    ->state(fn (MerchandiseOrderLine $record): HtmlString => $this->priceHtml(
                        $record->approvedUnitPrice(),
                        $record->listUnitPrice(),
                        $record->hasUnitDiscount(),
                    )),
                TextColumn::make('quantity')
                    ->label(__('Qty'))
                    ->alignEnd(),
                TextColumn::make('line_total')
                    ->label(__('Total'))
                    ->alignEnd()
                    ->html()
                    ->state(fn (MerchandiseOrderLine $record): HtmlString => $this->priceHtml(
                        $record->lineTotal(),
                        $record->listLineTotal(),
                        $record->hasUnitDiscount(),
                    ))
                    ->summarize([
                        Summarizer::make('subtotal')
                            ->label(__('Subtotal'))
                            ->visible(fn (): bool => $this->hasOrderDiscount())
                            ->using(function (Builder $query): float {
                                return (float) $query->sum(DB::raw(
                                    'COALESCE(approved_unit_price, unit_price) * quantity',
                                ));
                            })
                            ->money('GBP', locale: 'en_GB'),
                        Summarizer::make('order_discount')
                            ->label(__('Order discount'))
                            ->visible(fn (): bool => $this->hasOrderDiscount())
                            ->using(function (): float {
                                $order = $this->getOwnerRecord();

                                return $order instanceof MerchandiseOrder
                                    ? (float) $order->order_discount
                                    : 0.0;
                            })
                            ->money('GBP', locale: 'en_GB'),
                        Summarizer::make('total_due')
                            ->label(__('Total due'))
                            ->using(function (Builder $query): float {
                                $subtotal = (float) $query->sum(DB::raw(
                                    'COALESCE(approved_unit_price, unit_price) * quantity',
                                ));
                                $order = $this->getOwnerRecord();
                                $discount = $order instanceof MerchandiseOrder
                                    ? (float) $order->order_discount
                                    : 0.0;

                                return max(0, round($subtotal - $discount, 2));
                            })
                            ->money('GBP', locale: 'en_GB'),
                    ]),
            ])
            ->paginated(false)
            ->headerActions([
                $this->orderDiscountAction(),
                $this->approveAction(),
                $this->rejectAction(),
            ])
            ->recordActions([
                Action::make('discountLine')
                    ->label(__('Adjust price'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->iconButton()
                    ->modalHeading(__('Adjust price'))
                    ->modalIcon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (): bool => $this->canManageDiscounts())
                    ->fillForm(function (MerchandiseOrderLine $record): array {
                        $approved = $record->approvedUnitPrice();
                        $list = $record->listUnitPrice();

                        return [
                            'approved_unit_price' => number_format($approved, 2, '.', ''),
                            'discount' => number_format(max(0, $list - $approved), 2, '.', ''),
                        ];
                    })
                    ->schema(fn (MerchandiseOrderLine $record): array => [
                        self::moneyInput('approved_unit_price')
                            ->label(__('Price'))
                            ->required()
                            ->rules([
                                'numeric',
                                'min:0',
                                'decimal:0,2',
                                'lte:'.$record->listUnitPrice(),
                            ])
                            ->afterStateUpdated(function (TextInput $component, Set $set, mixed $state) use ($record): void {
                                $formatted = self::formatMoneyState($state);

                                if ($formatted !== null && (string) $state !== $formatted) {
                                    $component->state($formatted);
                                }

                                $approved = is_numeric($formatted ?? $state) ? (float) ($formatted ?? $state) : 0.0;
                                $set('discount', self::formatMoneyState(
                                    max(0, round($record->listUnitPrice() - $approved, 2)),
                                ));
                            })
                            ->helperText(__('List price :amount', [
                                'amount' => Number::currency($record->listUnitPrice(), 'GBP', 'en_GB'),
                            ])),
                        self::moneyInput('discount')
                            ->label(__('Discount'))
                            ->required()
                            ->rules([
                                'numeric',
                                'min:0',
                                'decimal:0,2',
                                'lte:'.$record->listUnitPrice(),
                            ])
                            ->afterStateUpdated(function (TextInput $component, Set $set, mixed $state) use ($record): void {
                                $formatted = self::formatMoneyState($state);

                                if ($formatted !== null && (string) $state !== $formatted) {
                                    $component->state($formatted);
                                }

                                $discount = is_numeric($formatted ?? $state) ? (float) ($formatted ?? $state) : 0.0;
                                $set('approved_unit_price', self::formatMoneyState(
                                    max(0, round($record->listUnitPrice() - $discount, 2)),
                                ));
                            }),
                    ])
                    ->action(function (MerchandiseOrderLine $record, array $data): void {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return;
                        }

                        app(SetMerchandiseOrderLinePriceAction::class)(
                            $record,
                            $user,
                            $data['approved_unit_price'] ?? $record->listUnitPrice(),
                        );

                        $this->refreshOrderTotals();
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('No items on this order.'));
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->lines()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function orderDiscountAction(): Action
    {
        return Action::make('orderDiscount')
            ->label(__('Order discount'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(__('Order discount'))
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->modalDescription(__('Take a value off the whole order when the credit does not belong to a single line.'))
            ->visible(fn (): bool => $this->canManageDiscounts())
            ->fillForm(function (): array {
                $order = $this->getOwnerRecord();

                return [
                    'order_discount' => $order instanceof MerchandiseOrder
                        ? number_format((float) $order->order_discount, 2, '.', '')
                        : '0.00',
                ];
            })
            ->schema([
                self::moneyInput('order_discount')
                    ->label(__('Order discount'))
                    ->required()
                    ->rules(['numeric', 'min:0', 'decimal:0,2'])
                    ->helperText(function (): ?string {
                        $order = $this->getOwnerRecord();

                        if (! $order instanceof MerchandiseOrder) {
                            return null;
                        }

                        $order->loadMissing('lines');

                        return __('Items subtotal :amount', [
                            'amount' => Number::currency($order->itemsSubtotal(), 'GBP', 'en_GB'),
                        ]);
                    }),
            ])
            ->action(function (array $data): void {
                $order = $this->getOwnerRecord();
                $user = auth()->user();

                if (! $order instanceof MerchandiseOrder || ! $user instanceof User) {
                    return;
                }

                app(SetMerchandiseOrderDiscountAction::class)(
                    $order,
                    $user,
                    $data['order_discount'] ?? 0,
                );

                $this->refreshOrderTotals();
            });
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color(Palette::positive())
            ->modalHeading(__('Approve order'))
            ->modalIcon(Heroicon::OutlinedCheckCircle)
            ->modalDescription(function (): ?string {
                $order = $this->getOwnerRecord();

                if (! $order instanceof MerchandiseOrder) {
                    return null;
                }

                $order->loadMissing('lines');

                return __('Total due :amount. Discounts already set on the items stay as they are.', [
                    'amount' => Number::currency($order->total(), 'GBP', 'en_GB'),
                ]);
            })
            ->schema([
                Textarea::make('approval_note')
                    ->label(__('Note'))
                    ->rows(3)
                    ->maxLength(2000)
                    ->helperText(__('Optional. Visible to managers on this order.'))
                    ->extraAlpineAttributes(SubmitsOnModEnter::attributes()),
            ])
            ->visible(function (): bool {
                $order = $this->getOwnerRecord();
                $user = auth()->user();

                return $order instanceof MerchandiseOrder
                    && $user instanceof User
                    && $user->can('approve', $order);
            })
            ->action(function (array $data): void {
                $order = $this->getOwnerRecord();
                $user = auth()->user();

                if (! $order instanceof MerchandiseOrder || ! $user instanceof User) {
                    return;
                }

                try {
                    app(ApproveMerchandiseOrderAction::class)(
                        $order,
                        $user,
                        $data['approval_note'] ?? null,
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title(collect($exception->errors())->flatten()->first() ?: __('Could not approve order.'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('Order approved'))
                    ->success()
                    ->send();

                $this->refreshOrderTotals();
            });
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('Reject'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color(Palette::destructive())
            ->modalHeading(__('Reject order'))
            ->modalIcon(Heroicon::OutlinedNoSymbol)
            ->modalDescription(__('Explain what needs to change. The member can reopen into their basket later to fix and submit again.'))
            ->schema([
                Textarea::make('rejection_reason')
                    ->label(__('Reason'))
                    ->rows(4)
                    ->required()
                    ->minLength(3)
                    ->maxLength(2000)
                    ->extraAlpineAttributes(SubmitsOnModEnter::attributes()),
            ])
            ->visible(function (): bool {
                $order = $this->getOwnerRecord();
                $user = auth()->user();

                return $order instanceof MerchandiseOrder
                    && $user instanceof User
                    && $user->can('reject', $order);
            })
            ->action(function (array $data): void {
                $order = $this->getOwnerRecord();
                $user = auth()->user();

                if (! $order instanceof MerchandiseOrder || ! $user instanceof User) {
                    return;
                }

                try {
                    app(RejectMerchandiseOrderAction::class)(
                        $order,
                        $user,
                        (string) ($data['rejection_reason'] ?? ''),
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title(collect($exception->errors())->flatten()->first() ?: __('Could not reject order.'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('Order rejected'))
                    ->success()
                    ->send();

                $this->refreshOrderTotals();
            });
    }

    protected function refreshOrderTotals(): void
    {
        $this->getOwnerRecord()?->refresh()->unsetRelation('lines');
        $this->dispatch('merchandise-order-totals-updated');
    }

    protected function canManageDiscounts(): bool
    {
        $order = $this->getOwnerRecord();
        $user = auth()->user();

        return $order instanceof MerchandiseOrder
            && $user instanceof User
            && $user->can('discount', $order);
    }

    protected function hasOrderDiscount(): bool
    {
        $order = $this->getOwnerRecord();

        return $order instanceof MerchandiseOrder
            && (float) $order->order_discount > 0;
    }

    private static function moneyInput(string $name): TextInput
    {
        // type=text so trailing pence stay visible; $money mask does not pad to 2dp on load.
        return TextInput::make($name)
            ->prefix('£')
            ->type('text')
            ->inputMode('decimal')
            ->extraAlpineAttributes([
                'x-on:blur' => <<<'JS'
                    if ($el.value === '' || Number.isNaN(Number($el.value))) {
                        return
                    }

                    const formatted = Number($el.value).toFixed(2)

                    if ($el.value !== formatted) {
                        $el.value = formatted
                        $el.dispatchEvent(new Event('input', { bubbles: true }))
                    }
                JS,
            ])
            ->formatStateUsing(fn (mixed $state): ?string => self::formatMoneyState($state))
            ->dehydrateStateUsing(fn (mixed $state): ?string => self::formatMoneyState($state))
            ->live(onBlur: true)
            ->afterStateUpdated(function (TextInput $component, mixed $state): void {
                $formatted = self::formatMoneyState($state);

                if ($formatted !== null && (string) $state !== $formatted) {
                    $component->state($formatted);
                }
            });
    }

    private static function formatMoneyState(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        if (! is_numeric($state)) {
            return null;
        }

        return number_format((float) $state, 2, '.', '');
    }

    private function priceHtml(float $approved, float $list, bool $discounted): HtmlString
    {
        $approvedLabel = Number::currency($approved, 'GBP', 'en_GB');

        if (! $discounted) {
            return new HtmlString(e($approvedLabel));
        }

        $listLabel = Number::currency($list, 'GBP', 'en_GB');
        $offLabel = Number::currency(max(0, round($list - $approved, 2)), 'GBP', 'en_GB');

        return new HtmlString(
            '<span class="inline-flex flex-col items-end gap-0.5">'
            .'<span class="font-semibold text-gray-950 dark:text-white">'.e($approvedLabel).'</span>'
            .'<span class="text-xs font-medium text-danger-600 dark:text-danger-400">−'.e($offLabel).'</span>'
            .'<span class="text-xs text-gray-500 line-through dark:text-gray-400">'.e($listLabel).'</span>'
            .'</span>'
        );
    }

    private static function productUrl(MerchandiseOrderLine $record): ?string
    {
        if ($record->item === null) {
            return null;
        }

        return MerchandiseShopResource::getUrl('view', ['record' => $record->item]);
    }
}
