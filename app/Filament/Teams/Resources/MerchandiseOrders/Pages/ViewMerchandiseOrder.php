<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\Pages;

use App\Actions\CancelMerchandiseOrderAction;
use App\Actions\MarkMerchandiseOrderPaidAction;
use App\Actions\RequestMerchandiseOrderTreasurerInvoiceAction;
use App\Filament\Teams\Resources\IssuedMerchandises\IssuedMerchandiseResource;
use App\Filament\Teams\Resources\MerchandiseOrders\MerchandiseOrderResource;
use App\Models\MerchandiseOrder;
use App\Models\User;
use App\Support\MarkSubjectInboxRead;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class ViewMerchandiseOrder extends ViewRecord
{
    protected static string $resource = MerchandiseOrderResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user = auth()->user();
        $order = $this->getRecord();

        if ($user instanceof User && $order instanceof MerchandiseOrder) {
            app(MarkSubjectInboxRead::class)($user, $order);
        }
    }

    #[On('merchandise-order-totals-updated')]
    public function refreshAfterTotalsChange(): void
    {
        $record = $this->getRecord();

        if ($record instanceof MerchandiseOrder) {
            $record->refresh()->unsetRelation('lines');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewIssued')
                ->label(__('Issued history'))
                ->icon(Heroicon::OutlinedGift)
                ->url(function (): ?string {
                    $record = $this->getRecord();

                    if (! $record instanceof MerchandiseOrder) {
                        return null;
                    }

                    // ListRecords exposes tableFilters on the query string as `filters`.
                    return IssuedMerchandiseResource::getUrl('index', [
                        'filters' => [
                            'user_id' => [
                                'value' => $record->user_id,
                            ],
                        ],
                    ]);
                })
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    $user = auth()->user();

                    if (! $record instanceof MerchandiseOrder || ! $user instanceof User) {
                        return false;
                    }

                    $record->loadMissing('team');

                    return $user->manages($record->team);
                }),
            Action::make('markPaid')
                ->label(__('Mark paid'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color(Palette::positive())
                ->requiresConfirmation()
                ->modalHeading(__('Mark order paid'))
                ->modalDescription(__('Confirm that payment has been received for this order.'))
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    $user = auth()->user();

                    return $record instanceof MerchandiseOrder
                        && $user instanceof User
                        && $user->can('markPaid', $record);
                })
                ->action(function (): void {
                    $record = $this->getRecord();
                    $user = auth()->user();

                    if (! $record instanceof MerchandiseOrder || ! $user instanceof User) {
                        return;
                    }

                    try {
                        app(MarkMerchandiseOrderPaidAction::class)($record, $user);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title(collect($exception->errors())->flatten()->first() ?: __('Could not mark order as paid.'))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('Order marked as paid'))
                        ->success()
                        ->send();

                    $this->getRecord()->refresh();
                }),
            Action::make('requestTreasurerInvoice')
                ->label(__('Request treasurer invoice'))
                ->icon(Heroicon::OutlinedEnvelope)
                ->modalHeading(__('Request treasurer invoice'))
                ->modalIcon(Heroicon::OutlinedEnvelope)
                ->modalDescription(__('Asks the treasurer to invoice the member. The member and you are copied. Reply-To is you.'))
                ->fillForm(function (): array {
                    $record = $this->getRecord();

                    if (! $record instanceof MerchandiseOrder) {
                        return [];
                    }

                    $record->loadMissing('team');

                    return [
                        'treasurer_email' => $record->team?->treasurer_email,
                    ];
                })
                ->schema([
                    TextInput::make('treasurer_email')
                        ->label(__('Treasurer email'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                ])
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    $user = auth()->user();

                    return $record instanceof MerchandiseOrder
                        && $user instanceof User
                        && $user->can('requestTreasurerInvoice', $record);
                })
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    $user = auth()->user();

                    if (! $record instanceof MerchandiseOrder || ! $user instanceof User) {
                        return;
                    }

                    try {
                        app(RequestMerchandiseOrderTreasurerInvoiceAction::class)(
                            $record,
                            $user,
                            (string) ($data['treasurer_email'] ?? ''),
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title(collect($exception->errors())->flatten()->first() ?: __('Could not send the request.'))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('Treasurer request queued'))
                        ->success()
                        ->send();
                }),
            Action::make('cancel')
                ->label(__('Cancel order'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    $user = auth()->user();

                    return $record instanceof MerchandiseOrder
                        && $user instanceof User
                        && $user->can('cancel', $record);
                })
                ->action(function (): void {
                    $record = $this->getRecord();
                    $user = auth()->user();

                    if (! $record instanceof MerchandiseOrder || ! $user instanceof User) {
                        return;
                    }

                    try {
                        app(CancelMerchandiseOrderAction::class)($record, $user);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title(collect($exception->errors())->flatten()->first() ?: __('Could not cancel order.'))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('Order cancelled'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
