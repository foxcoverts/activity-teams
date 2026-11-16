@php
    /** @var array{teams: int, activities: int, venues: int, audiences: int, keys: int} $snapshot */
    $items = [
        ['label' => __('Teams'), 'value' => $snapshot['teams']],
        ['label' => __('Activities'), 'value' => $snapshot['activities']],
        ['label' => __('Venues'), 'value' => $snapshot['venues']],
        ['label' => __('Audiences'), 'value' => $snapshot['audiences']],
        ['label' => __('Keys'), 'value' => $snapshot['keys']],
    ];
@endphp

<x-filament-widgets::widget>
    @if ($failed)
        @include('filament.member.widgets.failed')
    @else
        <x-filament::section :heading="$heading" :icon="$icon" :icon-color="$iconColor">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($items as $item)
                    <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                            {{ number_format($item['value']) }}
                        </p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
