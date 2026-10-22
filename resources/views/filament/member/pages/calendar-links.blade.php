<x-filament-panels::page>
    <x-filament::section>
        <p class="mb-4 text-sm text-gray-600">{{ __('Subscribe to these feeds in your calendar app. Treat them like passwords.') }}</p>
        <dl class="space-y-4">
            <div>
                <dt class="font-medium">{{ __('All bookings') }}</dt>
                <dd class="break-all text-sm">{{ $bookingsUrl }}</dd>
            </div>
            <div>
                <dt class="font-medium">{{ __('My rota') }}</dt>
                <dd class="break-all text-sm">{{ $rotaUrl }}</dd>
            </div>
        </dl>
    </x-filament::section>
</x-filament-panels::page>
