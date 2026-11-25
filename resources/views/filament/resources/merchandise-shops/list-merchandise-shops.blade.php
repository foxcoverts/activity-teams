@php
    use App\Models\MerchandiseItem;
    use Illuminate\Support\Number;

    $records = $this->getTableRecords();
@endphp
<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament-tables::search-field />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($records as $item)
                @php
                    /** @var MerchandiseItem $item */
                    $url = $this->getResource()::getUrl('view', ['record' => $item]);
                    $thumbnail = $item->thumbnailImage()?->thumbnailUrl();
                    $fromPrice = $item->lowestAvailablePrice();
                @endphp
                <a
                    href="{{ $url }}"
                    class="group overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 transition hover:ring-primary-500 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-400"
                >
                    <div class="aspect-square overflow-hidden bg-gray-100 dark:bg-gray-800">
                        @if ($thumbnail)
                            <img
                                src="{{ $thumbnail }}"
                                alt=""
                                class="h-full w-full object-cover transition group-hover:scale-[1.02]"
                            />
                        @else
                            <div class="flex h-full w-full items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                                {{ __('No image') }}
                            </div>
                        @endif
                    </div>
                    <div class="space-y-1 p-4">
                        <h2 class="text-base font-semibold text-gray-950 group-hover:underline dark:text-white">
                            {{ $item->title }}
                        </h2>
                        @if ($fromPrice !== null)
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                @if ($item->variants->count() > 1)
                                    {{ __('From :price', ['price' => Number::currency((float) $fromPrice, 'GBP', 'en_GB')]) }}
                                @else
                                    {{ Number::currency((float) $fromPrice, 'GBP', 'en_GB') }}
                                @endif
                            </p>
                        @endif
                    </div>
                </a>
            @empty
                <div class="col-span-full">
                    <x-filament::section>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ __('No products are available in the shop yet.') }}
                        </p>
                    </x-filament::section>
                </div>
            @endforelse
        </div>

        <x-filament::pagination :paginator="$records" />
    </div>
</x-filament-panels::page>
