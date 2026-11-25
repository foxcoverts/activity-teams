@php
    use App\Models\MerchandiseItem;
    use App\Models\MerchandiseItemImage;
    use App\Models\MerchandiseItemVariant;
    use Illuminate\Support\Js;
    use Illuminate\Support\Number;

    /** @var MerchandiseItem $item */
    $item = $this->getRecord();
    $images = $item->images;
    $variants = $item->variants;
    $initialImage = $images->first();
    $initialVariant = $variants->first();

    $imageUrls = $images
        ->mapWithKeys(fn (MerchandiseItemImage $image): array => [
            $image->getKey() => $image->url(),
        ])
        ->all();

    $variantPayload = $variants
        ->mapWithKeys(function (MerchandiseItemVariant $variant): array {
            $priceLabel = Number::currency((float) $variant->price, 'GBP', 'en_GB');

            return [
                $variant->getKey() => [
                    'label' => $variant->label,
                    'priceLabel' => $priceLabel,
                ],
            ];
        })
        ->all();
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <div
            class="grid gap-8 md:grid-cols-2 md:items-start"
            x-data="{
                currentImageId: {{ Js::from($initialImage?->getKey()) }},
                previewImageId: null,
                selectedVariantId: {{ Js::from($initialVariant?->getKey()) }},
                imageUrls: {{ Js::from($imageUrls) }},
                variants: {{ Js::from($variantPayload) }},
                get displayImageUrl() {
                    const id = this.previewImageId ?? this.currentImageId

                    return id ? this.imageUrls[id] ?? null : null
                },
                get selectedVariant() {
                    return this.selectedVariantId
                        ? this.variants[this.selectedVariantId] ?? null
                        : null
                },
            }"
        >
        <div class="flex gap-3 sm:gap-4">
            @if ($images->count() > 1)
                <div class="flex w-16 shrink-0 flex-col gap-2 sm:w-20" role="list">
                    @foreach ($images as $image)
                        <button
                            type="button"
                            role="listitem"
                            class="aspect-square overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/10 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:bg-gray-800 dark:ring-white/10"
                            :class="currentImageId === {{ Js::from($image->getKey()) }} ? 'ring-2 ring-primary-500 dark:ring-primary-400' : 'hover:ring-gray-950/20 dark:hover:ring-white/20'"
                            @mouseenter="previewImageId = {{ Js::from($image->getKey()) }}"
                            @mouseleave="previewImageId = null"
                            @focus="previewImageId = {{ Js::from($image->getKey()) }}"
                            @blur="previewImageId = null"
                            @click="currentImageId = {{ Js::from($image->getKey()) }}; previewImageId = null"
                        >
                            <img
                                src="{{ $image->thumbnailUrl() }}"
                                alt=""
                                class="h-full w-full object-cover"
                            />
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="min-w-0 flex-1 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                <div class="aspect-square">
                    <template x-if="displayImageUrl">
                        <img
                            :src="displayImageUrl"
                            alt=""
                            class="h-full w-full object-contain"
                        />
                    </template>
                    @if ($images->isEmpty())
                        <div class="flex h-full w-full items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                            {{ __('No image') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    {{ $item->title }}
                </h1>
                <p
                    class="text-sm text-gray-600 dark:text-gray-400"
                    x-show="selectedVariant"
                    x-text="selectedVariant?.label"
                    x-cloak
                ></p>
            </div>

            @if (filled($item->description))
                <div class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                    {{ $item->description }}
                </div>
            @endif

            <p
                class="text-xl font-semibold text-gray-950 dark:text-white"
                x-show="selectedVariant"
                x-text="selectedVariant?.priceLabel"
                x-cloak
            ></p>

            @if ($variants->count() > 1)
                <div class="max-w-sm space-y-1.5">
                    <label for="shop-variant" class="block text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('Variant') }}
                    </label>
                    <div class="relative">
                        <select
                            id="shop-variant"
                            x-model="selectedVariantId"
                            class="block w-full appearance-none rounded-lg border-none bg-white py-2 pl-3 pr-10 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-400"
                        >
                            @foreach ($variants as $variant)
                                @php
                                    $optionPrice = Number::currency((float) $variant->price, 'GBP', 'en_GB');
                                @endphp
                                <option value="{{ $variant->getKey() }}">
                                    {{ $variant->label }} - {{ $optionPrice }}
                                </option>
                            @endforeach
                        </select>
                        <svg
                            class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-gray-500 dark:text-gray-400"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>
                </div>
            @endif
        </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
