<x-filament-panels::page>
    @php
        $groups = $this->groups();
    @endphp

    <div
        class="equipment-sets-manage space-y-4"
        x-data
        x-init="
            if (typeof Sortable === 'undefined') {
                return;
            }

            Sortable.create($el, {
                animation: 150,
                handle: '[data-group-handle]',
                draggable: '[data-equipment-group]',
                ghostClass: 'fi-sortable-ghost',
                onEnd() {
                    const ids = [...$el.querySelectorAll(':scope > [data-equipment-group]')].map(
                        (node) => node.dataset.equipmentGroup,
                    );
                    $wire.reorderGroups(ids);
                },
            });
        "
    >
        @forelse ($groups as $group)
            @php
                $capabilities = $this->groupCapabilities($group);
            @endphp

            <div data-equipment-group="{{ $group->getKey() }}" wire:key="equipment-group-{{ $group->getKey() }}">
                <x-filament::section
                    class="equipment-set-group-panel"
                    :heading="$group->label"
                    :has-content-el="false"
                >
                    <x-slot name="afterHeader">
                        <div class="flex flex-wrap items-center gap-1">
                            <x-filament::icon-button
                                data-group-handle
                                icon="heroicon-o-bars-2"
                                :label="__('Drag to reorder group')"
                                color="gray"
                                size="sm"
                                class="cursor-grab"
                            />

                            @if ($capabilities['canMoveUp'])
                                <x-filament::icon-button
                                    icon="heroicon-o-arrow-up"
                                    :label="__('Move up')"
                                    color="gray"
                                    size="sm"
                                    wire:click="moveGroupUp('{{ $group->getKey() }}')"
                                />
                            @endif

                            @if ($capabilities['canMoveDown'])
                                <x-filament::icon-button
                                    icon="heroicon-o-arrow-down"
                                    :label="__('Move down')"
                                    color="gray"
                                    size="sm"
                                    wire:click="moveGroupDown('{{ $group->getKey() }}')"
                                />
                            @endif

                            <x-filament::icon-button
                                icon="heroicon-o-pencil-square"
                                :label="__('Edit group')"
                                color="gray"
                                size="sm"
                                wire:click="mountAction('editGroup', { group: '{{ $group->getKey() }}' })"
                            />

                            {{ ($this->addSetAction)(['group' => $group->getKey()]) }}

                            @if ($capabilities['canDelete'])
                                <x-filament::icon-button
                                    icon="heroicon-o-trash"
                                    :label="__('Remove group')"
                                    color="danger"
                                    size="sm"
                                    wire:click="mountAction('deleteGroup', { group: '{{ $group->getKey() }}' })"
                                />
                            @endif
                        </div>
                    </x-slot>

                    <div
                        class="equipment-set-list"
                        data-group-id="{{ $group->getKey() }}"
                        wire:key="equipment-sets-{{ $group->getKey() }}-{{ $group->equipmentSets->pluck('id')->join('-') }}"
                        x-data
                        x-init="
                            if (typeof Sortable === 'undefined') {
                                return;
                            }

                            Sortable.create($el, {
                                group: 'equipment-sets',
                                animation: 150,
                                draggable: '[data-set-id]',
                                handle: '[data-set-handle]',
                                ghostClass: 'fi-sortable-ghost',
                                onAdd(event) {
                                    const setId = event.item.dataset.setId;
                                    const groupId = $el.dataset.groupId;
                                    if (setId && groupId) {
                                        $wire.moveSetToGroup(setId, groupId);
                                    }
                                },
                            });
                        "
                    >
                        @forelse ($group->equipmentSets as $set)
                            <div class="invite-row" data-set-id="{{ $set->getKey() }}" wire:key="equipment-set-{{ $set->getKey() }}">
                                <div class="invite-person">
                                    <x-filament::icon-button
                                        data-set-handle
                                        icon="heroicon-o-bars-2"
                                        :label="__('Drag to another group')"
                                        color="gray"
                                        size="sm"
                                        class="cursor-grab"
                                    />
                                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $set->name }}</span>
                                    @foreach ($this->activityBadges($set) as $badge)
                                        <x-filament::badge :color="$badge['color']" size="sm">
                                            {{ $badge['label'] }}
                                        </x-filament::badge>
                                    @endforeach
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
                                        <x-filament::dropdown.list.item
                                            wire:click="mountAction('editSet', { set: '{{ $set->getKey() }}' })"
                                            :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare"
                                        >
                                            {{ __('Edit') }}
                                        </x-filament::dropdown.list.item>
                                        @if ($groups->count() > 1)
                                            <x-filament::dropdown.list.item
                                                wire:click="mountAction('moveSet', { set: '{{ $set->getKey() }}' })"
                                                :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowsRightLeft"
                                            >
                                                {{ __('Move to…') }}
                                            </x-filament::dropdown.list.item>
                                        @endif
                                        <x-filament::dropdown.list.item
                                            wire:click="mountAction('deleteSet', { set: '{{ $set->getKey() }}' })"
                                            color="danger"
                                            :icon="\Filament\Support\Icons\Heroicon::OutlinedTrash"
                                        >
                                            {{ __('Remove') }}
                                        </x-filament::dropdown.list.item>
                                    </x-filament::dropdown.list>
                                </x-filament::dropdown>
                            </div>
                        @empty
                            <p class="invite-empty">{{ __('No equipment sets in this group.') }}</p>
                        @endforelse
                    </div>
                </x-filament::section>
            </div>
        @empty
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No equipment set groups yet.') }}</p>
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
