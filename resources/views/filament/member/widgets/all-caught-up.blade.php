<x-filament-widgets::widget :class="($failed || ! $caughtUp) ? 'hidden' : ''">
    @if (! $failed && $caughtUp)
        <x-filament::section :icon="\Filament\Support\Icons\Heroicon::OutlinedCheckCircle" :icon-color="\App\Support\Palette::positive()">
            <p class="font-medium">{{ __('You\'re all caught up') }}</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('Nothing needs your attention right now.') }}
                <x-filament::link :href="$bookingsUrl">{{ __('Browse team bookings') }}</x-filament::link>
            </p>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
