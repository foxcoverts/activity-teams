<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\Pages;

use App\Actions\CancelMerchandiseOrderAction;
use App\Filament\Teams\Resources\MerchandiseOrders\MerchandiseOrderResource;
use App\Models\MerchandiseOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class ViewMerchandiseOrder extends ViewRecord
{
    protected static string $resource = MerchandiseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
