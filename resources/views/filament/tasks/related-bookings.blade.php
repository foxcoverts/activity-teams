<div>
    @if ($showPanel)
        <x-filament::section class="related-bookings-panel" :heading="__('Related bookings')" :has-content-el="false">
            <x-slot name="afterHeader">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($hiddenPastCount > 0 || $showPast)
                        <x-filament::button size="sm" color="gray" outlined wire:click="toggleShowPast">
                            {{ $showPast ? __('Hide past') : __('Show past') }}
                        </x-filament::button>
                    @endif
                    @if ($canManage)
                        {{ $this->addAction }}
                    @endif
                </div>
            </x-slot>

            @forelse ($items as $item)
                <div class="invite-row" wire:key="task-related-{{ $item['id'] }}">
                    <a href="{{ $item['url'] }}" class="related-booking-label">
                        {{ $item['label'] }}
                        @if ($item['past'])
                            <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">({{ __('past') }})</span>
                        @endif
                    </a>

                    @if ($canManage)
                        <x-filament::button size="sm" color="danger" outlined
                            wire:click="remove('{{ $item['id'] }}')">
                            {{ __('Remove') }}
                        </x-filament::button>
                    @endif
                </div>
            @empty
                <p class="invite-empty">
                    {{ $showPast ? __('No related bookings.') : __('No upcoming related bookings.') }}
                </p>
            @endforelse
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</div>
