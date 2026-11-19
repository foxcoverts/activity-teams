<x-filament-widgets::widget>
    @if ($failed)
        @include('filament.member.widgets.failed')
    @else
        <x-filament::section :heading="$heading" :icon="$icon" :icon-color="$iconColor">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($items as $item)
                    <a href="{{ $item['href'] }}"
                        class="block group rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 hover:bg-gray-100 dark:hover:bg-white/10 active:bg-gray-50 dark:active:bg-white/5">
                        <span
                            class="block text-sm font-medium text-gray-500 dark:text-gray-400 group-hover:text-gray-950 dark:group-hover:text-white">{{ $item['label'] }}</span>
                        <span class="block mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                            {{ number_format($item['value']) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
