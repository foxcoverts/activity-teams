<x-filament-panels::page>
    <x-filament::section>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ __('Subscribe to these feeds in your calendar app or news reader. Treat them like passwords.') }}</p>
        <dl class="space-y-4">
            <div>
                <dt class="font-medium">{{ __('All bookings') }}</dt>
                <dd class="break-all text-sm">{{ $bookingsUrl }}</dd>
            </div>
            <div>
                <dt class="font-medium">{{ __('My rota') }}</dt>
                <dd class="break-all text-sm">{{ $rotaUrl }}</dd>
            </div>
            <div>
                <dt class="font-medium">{{ __('News (Atom)') }}</dt>
                <dd class="break-all text-sm">{{ $atomUrl }}</dd>
            </div>
            <div>
                <dt class="font-medium">{{ __('News (RSS)') }}</dt>
                <dd class="break-all text-sm">{{ $rssUrl }}</dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-panels::page>
