<div>
    @if ($showPanel)
        <x-filament::section class="related-bookings-panel" :heading="__('Related bookings')" :has-content-el="false">
            @if ($canManage)
                <x-slot name="afterHeader">
                    {{ $this->addAction }}
                </x-slot>
            @endif

            @forelse ($items as $item)
                <div class="invite-row" wire:key="related-{{ $item['id'] }}">
                    <a href="{{ $item['url'] }}" class="related-booking-label">
                        {{ $item['label'] }}
                    </a>

                    @if ($canManage)
                        <x-filament::button size="sm" color="danger" outlined
                            wire:click="remove('{{ $item['id'] }}')">
                            {{ __('Remove') }}
                        </x-filament::button>
                    @endif
                </div>
            @empty
                <p class="invite-empty">{{ __('No related bookings.') }}</p>
            @endforelse
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</div>
