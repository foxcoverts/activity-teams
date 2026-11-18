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
                        <x-filament::dropdown placement="bottom-end">
                            <x-slot name="trigger">
                                <x-filament::icon-button
                                    icon="heroicon-o-ellipsis-vertical"
                                    :label="__('Actions')"
                                    color="gray"
                                    size="sm"
                                />
                            </x-slot>

                            <x-filament::dropdown.list>
                                <x-filament::dropdown.list.item
                                    wire:click="remove('{{ $item['id'] }}')"
                                    color="danger"
                                >
                                    {{ __('Remove') }}
                                </x-filament::dropdown.list.item>
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    @endif
                </div>
            @empty
                <p class="invite-empty">{{ __('No related bookings.') }}</p>
            @endforelse
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</div>
