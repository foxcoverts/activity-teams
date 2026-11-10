<div>
    @if ($showPanel)
        <x-filament::section
            class="booking-tasks-panel"
            :heading="__('Tasks')"
            :has-content-el="false"
        >
            @if ($canManage)
                <x-slot name="afterHeader">
                    {{ $this->addGroup() }}
                </x-slot>
            @endif

            @forelse ($items as $item)
                <div class="task-row" wire:key="booking-task-{{ $item['id'] }}-{{ $item['task']->status->value }}">
                    @include('filament.tasks.complete-control', [
                        'task' => $item['task'],
                        'canToggle' => $item['canToggle'],
                    ])

                    <div class="min-w-0">
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}" class="task-row__title hover:underline">
                                {{ $item['summary'] }}
                            </a>
                        @else
                            <div class="task-row__title">{{ $item['summary'] }}</div>
                        @endif
                        @if ($item['overdue'] || $item['priority'])
                            <div class="task-row__meta">
                                @if ($item['overdue'])
                                    <span class="text-danger-600 dark:text-danger-400">{{ __('Overdue') }}</span>
                                @endif
                                @if ($item['overdue'] && $item['priority'])
                                    <span aria-hidden="true"> · </span>
                                @endif
                                @if ($item['priority'])
                                    <span>{{ $item['priority'] }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

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
                            @if ($item['url'])
                                <x-filament::dropdown.list.item :href="$item['url']" tag="a">
                                    {{ __('Open task') }}
                                </x-filament::dropdown.list.item>
                            @endif
                            @if ($canManage)
                                <x-filament::dropdown.list.item
                                    wire:click="remove('{{ $item['id'] }}')"
                                    color="danger"
                                >
                                    {{ __('Remove') }}
                                </x-filament::dropdown.list.item>
                            @endif
                        </x-filament::dropdown.list>
                    </x-filament::dropdown>
                </div>
            @empty
                <p class="invite-empty">{{ __('No tasks on this booking.') }}</p>
            @endforelse
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</div>
