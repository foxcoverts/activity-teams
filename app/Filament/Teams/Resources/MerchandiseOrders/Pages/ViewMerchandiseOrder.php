<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\Pages;

use App\Actions\CancelMerchandiseOrderAction;
use App\Filament\Teams\Resources\IssuedMerchandises\IssuedMerchandiseResource;
use App\Filament\Teams\Resources\MerchandiseOrders\MerchandiseOrderResource;
use App\Models\MerchandiseOrder;
use App\Models\User;
use App\Support\MarkSubjectInboxRead;
use Filament\Actions\Action;
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
