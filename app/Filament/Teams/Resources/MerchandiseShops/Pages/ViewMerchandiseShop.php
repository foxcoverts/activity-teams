<?php

namespace App\Filament\Teams\Resources\MerchandiseShops\Pages;

use App\Actions\AddMerchandiseToBasketAction;
use App\Filament\Support\RefreshesNavigationChrome;
use App\Filament\Teams\Resources\MerchandiseBasketLines\MerchandiseBasketLineResource;
use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemVariant;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

class ViewMerchandiseShop extends ViewRecord
{
    protected static string $resource = MerchandiseShopResource::class;

    protected string $view = 'filament.resources.merchandise-shops.view-merchandise-shop';

    public string $selectedVariantId = '';

    public int $quantity = 1;

    /**
     * @var array<string, string>
     */
    public array $personalisation = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $item = $this->getRecord();

        if (! $item instanceof MerchandiseItem) {
            return;
        }

        $this->selectedVariantId = (string) ($item->variants->first()?->getKey() ?? '');
        $this->quantity = 1;
        $this->personalisation = collect($item->personalisationFieldDefinitions())
            ->mapWithKeys(fn (array $field): array => [$field['key'] => ''])
            ->all();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function updatedSelectedVariantId(): void
    {
        unset($this->selectedVariant);
    }

    #[Computed]
    public function selectedVariant(): ?MerchandiseItemVariant
    {
        $item = $this->getRecord();

        if (! $item instanceof MerchandiseItem || blank($this->selectedVariantId)) {
            return null;
        }

        return $item->variants->firstWhere(
            fn (MerchandiseItemVariant $variant): bool => (string) $variant->getKey() === $this->selectedVariantId,
        );
    }

    public function selectedVariantPriceLabel(): ?string
    {
        $variant = $this->selectedVariant;

        if (! $variant instanceof MerchandiseItemVariant) {
            return null;
        }

        return Number::currency((float) $variant->price, 'GBP', 'en_GB');
    }

    public function addToBasket(): void
    {
        $item = $this->getRecord();
        $team = Filament::getTenant();
        $user = auth()->user();
        $variant = $this->selectedVariant;

        if (! $item instanceof MerchandiseItem
            || ! $team instanceof Team
            || ! $user instanceof User
            || ! $variant instanceof MerchandiseItemVariant
        ) {
            Notification::make()
                ->title(__('Could not add to basket.'))
                ->danger()
                ->send();

            return;
        }

        try {
            app(AddMerchandiseToBasketAction::class)(
                $team,
                $user,
                $item,
                $variant,
                $this->quantity,
                $this->personalisation,
            );
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(collect($exception->errors())->flatten()->first() ?: __('Could not add to basket.'))
                ->danger()
                ->send();

            return;
        }

        RefreshesNavigationChrome::dispatch($this);

        Notification::make()
            ->title(__('Added to basket'))
            ->success()
            ->actions([
                Action::make('viewBasket')
                    ->label(__('View basket'))
                    ->url(MerchandiseBasketLineResource::getUrl('index')),
            ])
            ->send();
    }
}
