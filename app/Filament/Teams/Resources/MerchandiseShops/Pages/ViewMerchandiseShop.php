<?php

namespace App\Filament\Teams\Resources\MerchandiseShops\Pages;

use App\Actions\AddMerchandiseToBasketAction;
use App\Enums\MerchandiseAvailability;
use App\Filament\Support\RefreshesNavigationChrome;
use App\Filament\Teams\Resources\MerchandiseBasketLines\MerchandiseBasketLineResource;
use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemOptionAxis;
use App\Models\MerchandiseItemOptionValue;
use App\Models\MerchandiseItemVariant;
use App\Models\Team;
use App\Models\User;
use App\Support\MerchandiseVariantOptions;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

class ViewMerchandiseShop extends ViewRecord
{
    protected static string $resource = MerchandiseShopResource::class;

    protected string $view = 'filament.resources.merchandise-shops.view-merchandise-shop';

    public string $selectedVariantId = '';

    /**
     * Selected option value id keyed by axis id (multi-axis items).
     *
     * @var array<string, string>
     */
    public array $selectedOptionValueIds = [];

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

        $this->quantity = 1;
        $this->personalisation = collect($item->personalisationFieldDefinitions())
            ->mapWithKeys(fn (array $field): array => [$field['key'] => ''])
            ->all();

        if ($item->hasOptionAxes()) {
            $this->selectedOptionValueIds = [];
            $this->selectedVariantId = '';
            $this->autoSelectSoleOptionValues();
            $this->resolveSelectedVariantFromOptions();

            return;
        }

        $this->selectedVariantId = (string) ($item->variants->first()?->getKey() ?? '');
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

    public function updatedSelectedOptionValueIds(mixed $value, ?string $key): void
    {
        $item = $this->getRecord();

        if (! $item instanceof MerchandiseItem || blank($key)) {
            $this->autoSelectSoleOptionValues();
            $this->resolveSelectedVariantFromOptions();

            return;
        }

        $clear = false;

        foreach ($item->optionAxes as $axis) {
            $axisId = (string) $axis->getKey();

            if ($clear) {
                unset($this->selectedOptionValueIds[$axisId]);

                continue;
            }

            if ($axisId === (string) $key) {
                $clear = true;
            }
        }

        $this->autoSelectSoleOptionValues();
        $this->resolveSelectedVariantFromOptions();
    }

    public function usesOptionAxes(): bool
    {
        $item = $this->getRecord();

        return $item instanceof MerchandiseItem && $item->hasOptionAxes();
    }

    /**
     * @return Collection<int, MerchandiseItemOptionAxis>
     */
    public function optionAxes(): Collection
    {
        $item = $this->getRecord();

        if (! $item instanceof MerchandiseItem) {
            return collect();
        }

        return $item->optionAxes;
    }

    /**
     * @return Collection<int, MerchandiseItemOptionValue>
     */
    public function availableValuesForAxis(MerchandiseItemOptionAxis $axis): Collection
    {
        $item = $this->getRecord();

        if (! $item instanceof MerchandiseItem) {
            return collect();
        }

        return MerchandiseVariantOptions::availableValuesForAxis(
            $item,
            $axis,
            $this->selectedOptionValueIds,
            availableOnly: true,
        );
    }

    /**
     * When an axis has only one available value for the current partial selection, pick it.
     */
    protected function autoSelectSoleOptionValues(): void
    {
        $item = $this->getRecord();

        if (! $item instanceof MerchandiseItem) {
            return;
        }

        foreach ($item->optionAxes as $axis) {
            $axisId = (string) $axis->getKey();
            $available = $this->availableValuesForAxis($axis);
            $current = $this->selectedOptionValueIds[$axisId] ?? null;

            if (filled($current) && ! $available->contains(
                fn (MerchandiseItemOptionValue $value): bool => (string) $value->getKey() === (string) $current,
            )) {
                unset($this->selectedOptionValueIds[$axisId]);
                $current = null;
            }

            if (blank($current) && $available->count() === 1) {
                $this->selectedOptionValueIds[$axisId] = (string) $available->first()->getKey();
            }
        }
    }

    protected function resolveSelectedVariantFromOptions(): void
    {
        $item = $this->getRecord();

        if (! $item instanceof MerchandiseItem) {
            $this->selectedVariantId = '';
            unset($this->selectedVariant);

            return;
        }

        $variant = MerchandiseVariantOptions::resolveVariant($item, $this->selectedOptionValueIds);

        if ($variant instanceof MerchandiseItemVariant
            && $variant->availability === MerchandiseAvailability::Available
        ) {
            $this->selectedVariantId = (string) $variant->getKey();
        } else {
            $this->selectedVariantId = '';
        }

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

    public function variantSubtitle(): ?string
    {
        $variant = $this->selectedVariant;

        if ($variant instanceof MerchandiseItemVariant) {
            return $variant->label;
        }

        $item = $this->getRecord();

        if ($item instanceof MerchandiseItem && $item->lowestAvailablePrice() !== null) {
            return __('From');
        }

        return null;
    }

    public function selectedVariantPriceLabel(): ?string
    {
        $variant = $this->selectedVariant;

        if ($variant instanceof MerchandiseItemVariant) {
            return Number::currency((float) $variant->price, 'GBP', 'en_GB');
        }

        $item = $this->getRecord();

        if (! $item instanceof MerchandiseItem) {
            return null;
        }

        $fromPrice = $item->lowestAvailablePrice();

        if ($fromPrice === null) {
            return null;
        }

        return Number::currency((float) $fromPrice, 'GBP', 'en_GB');
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
