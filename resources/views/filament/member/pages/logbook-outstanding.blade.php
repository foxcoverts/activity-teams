<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
        <div class="flex items-center gap-3">
            <x-filament::icon
                icon="heroicon-o-clipboard-document-list"
                class="fi-section-header-icon h-6 w-6 text-gray-400 dark:text-gray-500"
            />
            <h3 class="fi-section-header-heading text-base font-semibold text-gray-950 dark:text-white">
                {{ __('Outstanding') }}
            </h3>
        </div>
        <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
            {{ __('Accepted sessions that are not yet in your logbook. Confirm attendance on the booking first, or speak to the lead or team manager.') }}
        </p>
    </div>

    <div class="fi-section-content-ctn border-t border-gray-200 dark:border-white/10">
        <div class="fi-section-content p-6">
            @forelse ($this->outstandingBookings() as $booking)
                <div
                    class="flex flex-col gap-3 border-b border-gray-100 py-4 first:pt-0 last:border-b-0 last:pb-0 dark:border-white/5 sm:flex-row sm:items-center sm:justify-between"
                    wire:key="logbook-outstanding-{{ $booking->getKey() }}"
                >
                    <div class="min-w-0">
                        <div class="font-medium text-gray-950 dark:text-white">
                            {{ $booking->activityTitle() }}
                        </div>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $booking->start_at?->copy()->timezone($booking->timezone)->toFormattedDayDateString() }}
                            {{ $booking->start_at?->copy()->timezone($booking->timezone)->format('H:i') }}
                            @if ($booking->venue)
                                · {{ $booking->venue->name }}
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-filament::button
                            size="sm"
                            color="success"
                            wire:click="mountAction('add', { booking: '{{ $booking->getKey() }}' })"
                        >
                            {{ __('Add') }}
                        </x-filament::button>
                        <x-filament::button
                            size="sm"
                            color="gray"
                            outlined
                            wire:click="mountAction('ignore', { booking: '{{ $booking->getKey() }}' })"
                        >
                            {{ __('Ignore') }}
                        </x-filament::button>
                        @if (app(\App\Support\Logbook::class)->canDecline($booking))
                            <x-filament::button
                                size="sm"
                                color="danger"
                                outlined
                                wire:click="mountAction('decline', { booking: '{{ $booking->getKey() }}' })"
                            >
                                {{ __('Decline') }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Nothing outstanding. When you accept a session and its day arrives, it will appear here until you add or ignore it.') }}
                </p>
            @endforelse
        </div>
    </div>
</div>
