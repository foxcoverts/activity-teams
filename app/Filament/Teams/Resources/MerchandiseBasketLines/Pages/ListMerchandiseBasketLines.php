<?php

namespace App\Filament\Teams\Resources\MerchandiseBasketLines\Pages;

use App\Actions\SubmitMerchandiseOrderAction;
use App\Filament\Support\RefreshesNavigationChrome;
use App\Filament\Teams\Resources\MerchandiseBasketLines\MerchandiseBasketLineResource;
use App\Filament\Teams\Resources\MerchandiseOrders\MerchandiseOrderResource;
use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use App\Models\MerchandiseBasketLine;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ListMerchandiseBasketLines extends ListRecords
{
    protected static string $resource = MerchandiseBasketLineResource::class;

    public function getTitle(): string
    {
        return __('Basket');
    }

    /**
     * @return array<string, string|null>
     */
    public function getBreadcrumbs(): array
    {
        return [
            MerchandiseShopResource::getUrl() => MerchandiseShopResource::getNavigationLabel() ?? __('Shop'),
            ...parent::getBreadcrumbs(),
        ];
    }

    public function getFooter(): ?View
    {
        return view('filament.resources.merchandise-basket-lines.footer');
    }

    public function continueShoppingAction(): Action
    {
        return Action::make('continueShopping')
            ->label(__('Continue shopping'))
            ->color('gray')
            ->url(fn (): string => MerchandiseShopResource::getUrl('index'));
    }

    public function submitOrderAction(): Action
    {
        return Action::make('submitOrder')
            ->label(__('Submit order'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(__('Submit order'))
            ->modalDescription(fn (): Htmlable => $this->submitOrderConfirmationDescription())
            ->disabled(fn (): bool => ! $this->hasBasketLines())
            ->action(function (): void {
                $team = Filament::getTenant();
                $user = auth()->user();

                if (! $team instanceof Team || ! $user instanceof User) {
                    return;
                }

                try {
                    $order = app(SubmitMerchandiseOrderAction::class)($team, $user);
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title(collect($exception->errors())->flatten()->first() ?: __('Could not submit order.'))
                        ->danger()
                        ->send();

                    return;
                }

                RefreshesNavigationChrome::dispatch($this);

                Notification::make()
                    ->title(__('Order submitted'))
                    ->success()
                    ->send();

                $this->redirect(MerchandiseOrderResource::getUrl('view', ['record' => $order]));
            });
    }

    public function removeAllItemsAction(): Action
    {
        return Action::make('removeAllItems')
            ->label(__('Remove all items'))
            ->link()
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('Remove all items'))
            ->modalDescription(__('Remove every item from your basket?'))
            ->modalSubmitActionLabel(__('Remove all'))
            ->visible(fn (): bool => $this->hasBasketLines())
            ->action(function (): void {
                $team = Filament::getTenant();
                $user = auth()->user();

                if (! $team instanceof Team || ! $user instanceof User) {
                    return;
                }

                MerchandiseBasketLine::query()
                    ->forTeamUser($team, $user)
                    ->delete();

                RefreshesNavigationChrome::dispatch($this);
                $this->resetTable();

                Notification::make()
                    ->title(__('Basket cleared'))
                    ->success()
                    ->send();
            });
    }

    private function hasBasketLines(): bool
    {
        $team = Filament::getTenant();
        $user = auth()->user();

        if (! $team instanceof Team || ! $user instanceof User) {
            return false;
        }

        return MerchandiseBasketLine::query()
            ->forTeamUser($team, $user)
            ->exists();
    }

    /**
     * Soft next-step copy for submit confirm. Revisit as US-83/84 mature (amend after
     * submit, approval/invoice/handover status, and any member-facing outcome notice).
     */
    private function submitOrderConfirmationDescription(): Htmlable
    {
        $team = Filament::getTenant();
        $managerCount = $team instanceof Team ? $team->activeManagers()->count() : 0;

        $review = $managerCount === 1
            ? __('Your team’s manager will review this order.')
            : __('Your team’s managers will review this order.');

        $cancel = __('After you submit, you can cancel the order from Orders while it is waiting for review.');

        return new HtmlString(
            '<div class="space-y-2">'
            .'<p>'.e($review).'</p>'
            .'<p>'.e($cancel).'</p>'
            .'</div>'
        );
    }
}
