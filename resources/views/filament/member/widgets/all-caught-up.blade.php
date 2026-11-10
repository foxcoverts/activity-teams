<x-filament-widgets::widget :class="$failed || !$caughtUp ? 'hidden' : ''">
    @if (!$failed && $caughtUp)
        <x-filament::section
            :heading="__('You\'re all caught up')"
            :icon="\Filament\Support\Icons\Heroicon::OutlinedCheckCircle"
            :icon-color="\App\Support\Palette::positive()">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('Nothing needs your attention right now.') }}
                @if (! empty($bookingsUrl))
                    <x-filament::link :href="$bookingsUrl">{{ __('Browse team bookings') }}</x-filament::link>
                @elseif ($hasTeams ?? false)
                    {{ __('Open a team below to get started.') }}
                @endif
            </p>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
