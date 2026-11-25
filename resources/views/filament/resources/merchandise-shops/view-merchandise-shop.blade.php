@php
    use App\Models\MerchandiseItem;
    use App\Models\MerchandiseItemImage;
    use Illuminate\Support\Js;

    /** @var MerchandiseItem $item */
    $item = $this->getRecord();
    $images = $item->images;
    $variants = $item->variants;
    $initialImage = $images->first();
    $personalisationFields = $item->personalisationFieldDefinitions();

    $imageIds = $images->map(fn (MerchandiseItemImage $image): string => $image->getKey())->values()->all();

    $imageUrls = $images
        ->mapWithKeys(fn (MerchandiseItemImage $image): array => [
            $image->getKey() => $image->url(),
        ])
        ->all();
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-8 md:grid-cols-2 md:items-start">
            <div
                class="flex gap-3 sm:gap-4"
                x-data="{
                    currentImageId: {{ Js::from($initialImage?->getKey()) }},
                    previewImageId: null,
                    imageIds: {{ Js::from($imageIds) }},
                    imageUrls: {{ Js::from($imageUrls) }},
                    lightboxOpen: false,
                    lightboxImageId: null,
                    lightboxScale: 1,
                    lightboxX: 0,
                    lightboxY: 0,
                    lightboxDragging: false,
                    lightboxDragStartX: 0,
                    lightboxDragStartY: 0,
                    lightboxOriginX: 0,
                    lightboxOriginY: 0,
                    minLightboxScale: 1,
                    maxLightboxScale: 5,
                    get displayImageId() {
                        return this.previewImageId ?? this.currentImageId
                    },
                    get displayImageUrl() {
                        const id = this.displayImageId

                        return id ? this.imageUrls[id] ?? null : null
                    },
                    get lightboxImageUrl() {
                        return this.lightboxImageId
                            ? this.imageUrls[this.lightboxImageId] ?? null
                            : null
                    },
                    get lightboxIndex() {
                        return this.imageIds.indexOf(this.lightboxImageId)
                    },
                    get lightboxTransform() {
                        return 'translate(' + this.lightboxX + 'px, ' + this.lightboxY + 'px) scale(' + this.lightboxScale + ')'
                    },
                    resetLightboxTransform() {
                        this.lightboxScale = 1
                        this.lightboxX = 0
                        this.lightboxY = 0
                        this.lightboxDragging = false
                    },
                    openLightbox(id = null) {
                        const target = id ?? this.displayImageId

                        if (! target || ! this.imageUrls[target]) {
                            return
                        }

                        this.lightboxImageId = target
                        this.resetLightboxTransform()
                        this.lightboxOpen = true
                    },
                    closeLightbox() {
                        this.lightboxOpen = false
                        this.lightboxImageId = null
                        this.resetLightboxTransform()
                    },
                    showLightboxAt(index) {
                        if (this.imageIds.length === 0) {
                            return
                        }

                        const normalised = (index + this.imageIds.length) % this.imageIds.length
                        this.lightboxImageId = this.imageIds[normalised]
                        this.resetLightboxTransform()
                    },
                    previousLightboxImage() {
                        this.showLightboxAt(this.lightboxIndex - 1)
                    },
                    nextLightboxImage() {
                        this.showLightboxAt(this.lightboxIndex + 1)
                    },
                    onLightboxWheel(event) {
                        event.preventDefault()

                        const factor = event.deltaY < 0 ? 1.12 : 1 / 1.12
                        const next = Math.min(
                            this.maxLightboxScale,
                            Math.max(this.minLightboxScale, this.lightboxScale * factor),
                        )

                        this.lightboxScale = next

                        if (next === this.minLightboxScale) {
                            this.lightboxX = 0
                            this.lightboxY = 0
                        }
                    },
                    onLightboxPointerDown(event) {
                        if (this.lightboxScale <= 1 || event.button !== 0) {
                            return
                        }

                        this.lightboxDragging = true
                        this.lightboxDragStartX = event.clientX
                        this.lightboxDragStartY = event.clientY
                        this.lightboxOriginX = this.lightboxX
                        this.lightboxOriginY = this.lightboxY
                        event.currentTarget.setPointerCapture?.(event.pointerId)
                    },
                    onLightboxPointerMove(event) {
                        if (! this.lightboxDragging) {
                            return
                        }

                        this.lightboxX = this.lightboxOriginX + (event.clientX - this.lightboxDragStartX)
                        this.lightboxY = this.lightboxOriginY + (event.clientY - this.lightboxDragStartY)
                    },
                    onLightboxPointerUp(event) {
                        if (! this.lightboxDragging) {
                            return
                        }

                        this.lightboxDragging = false
                        event.currentTarget.releasePointerCapture?.(event.pointerId)
                    },
                    toggleLightboxZoom() {
                        if (this.lightboxScale > 1) {
                            this.resetLightboxTransform()

                            return
                        }

                        this.lightboxScale = 2.5
                    },
                }"
                @keydown.escape.window="if (lightboxOpen) closeLightbox()"
                @keydown.left.window="if (lightboxOpen && lightboxScale <= 1 && imageIds.length > 1) previousLightboxImage()"
                @keydown.right.window="if (lightboxOpen && lightboxScale <= 1 && imageIds.length > 1) nextLightboxImage()"
            >
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
                            <button
                                type="button"
                                class="group relative h-full w-full cursor-zoom-in focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
                                @click="openLightbox()"
                                :aria-label="{{ Js::from(__('View larger image')) }}"
                            >
                                <img
                                    :src="displayImageUrl"
                                    alt=""
                                    class="h-full w-full object-contain"
                                />
                            </button>
                        </template>
                        @if ($images->isEmpty())
                            <div class="flex h-full w-full items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                                {{ __('No image') }}
                            </div>
                        @endif
                    </div>
                </div>

                <template x-teleport="body">
                    <div
                        x-show="lightboxOpen"
                        x-cloak
                        class="fixed inset-0 z-[100] flex items-center justify-center overflow-hidden p-4 sm:p-8"
                        role="dialog"
                        aria-modal="true"
                        aria-label="{{ __('Product image') }}"
                        @click.self="closeLightbox()"
                        @wheel.prevent="onLightboxWheel($event)"
                    >
                        <div class="absolute inset-0 bg-gray-950/80" @click="closeLightbox()"></div>

                        <button
                            type="button"
                            class="absolute right-4 top-4 z-10 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            @click="closeLightbox()"
                            aria-label="{{ __('Close') }}"
                        >
                            <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                            </svg>
                        </button>

                        @if ($images->count() > 1)
                            <button
                                type="button"
                                class="absolute left-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:left-6"
                                x-show="lightboxScale <= 1"
                                @click.stop="previousLightboxImage()"
                                aria-label="{{ __('Previous image') }}"
                            >
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="absolute right-3 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:right-6"
                                x-show="lightboxScale <= 1"
                                @click.stop="nextLightboxImage()"
                                aria-label="{{ __('Next image') }}"
                            >
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        @endif

                        <div
                            class="relative z-10 flex max-h-full max-w-full touch-none select-none items-center justify-center"
                            @click.stop
                            @pointerdown="onLightboxPointerDown($event)"
                            @pointermove="onLightboxPointerMove($event)"
                            @pointerup="onLightboxPointerUp($event)"
                            @pointercancel="onLightboxPointerUp($event)"
                            @dblclick.prevent="toggleLightboxZoom()"
                            :class="lightboxScale > 1 ? (lightboxDragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-zoom-in'"
                        >
                            <template x-if="lightboxImageUrl">
                                <img
                                    :src="lightboxImageUrl"
                                    alt=""
                                    draggable="false"
                                    class="max-h-[min(90vh,1600px)] max-w-[min(100%,1600px)] object-contain shadow-2xl will-change-transform"
                                    :style="'transform: ' + lightboxTransform"
                                />
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="space-y-4">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                        {{ $item->title }}
                    </h1>
                    @if ($this->selectedVariant)
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $this->selectedVariant->label }}
                        </p>
                    @endif
                </div>

                @if (filled($item->description))
                    <div class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                        {{ $item->description }}
                    </div>
                @endif

                @if ($this->selectedVariantPriceLabel())
                    <p class="text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $this->selectedVariantPriceLabel() }}
                    </p>
                @endif

                <form wire:submit="addToBasket" class="max-w-sm space-y-4">
                    @if ($variants->count() > 1)
                        <div class="space-y-1.5">
                            <label for="shop-variant" class="block text-sm font-medium text-gray-950 dark:text-white">
                                {{ __('Variant') }}
                            </label>
                            <div class="relative">
                                <select
                                    id="shop-variant"
                                    wire:model.live="selectedVariantId"
                                    class="block w-full appearance-none rounded-lg border-none bg-white py-2 pl-3 pr-10 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-400"
                                >
                                    @foreach ($variants as $variant)
                                        @php
                                            $optionPrice = \Illuminate\Support\Number::currency((float) $variant->price, 'GBP', 'en_GB');
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

                    @foreach ($personalisationFields as $field)
                        <div class="space-y-1.5">
                            <label for="personalisation-{{ $field['key'] }}" class="block text-sm font-medium text-gray-950 dark:text-white">
                                {{ $field['label'] }}
                                @if ($field['required'])
                                    <span class="text-danger-600">*</span>
                                @endif
                            </label>
                            <input
                                id="personalisation-{{ $field['key'] }}"
                                type="text"
                                wire:model="personalisation.{{ $field['key'] }}"
                                @required($field['required'])
                                class="block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-400"
                            />
                        </div>
                    @endforeach

                    <div class="space-y-1.5">
                        <label for="shop-quantity" class="block text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('Quantity') }}
                        </label>
                        <input
                            id="shop-quantity"
                            type="number"
                            min="1"
                            step="1"
                            wire:model="quantity"
                            required
                            class="block w-24 rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-400"
                        />
                    </div>

                    <x-filament::button type="submit" icon="heroicon-o-shopping-cart">
                        {{ __('Add to basket') }}
                    </x-filament::button>
                </form>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
