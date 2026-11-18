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
                    :has-content-el="false"
                >
                    <x-slot name="heading">
                        <div class="equipment-set-group-heading">
                            <button
                                type="button"
                                data-group-handle
                                class="equipment-set-group-handle"
                                title="{{ __('Drag to reorder group') }}"
                                aria-label="{{ __('Drag to reorder group') }}"
                            >
                                <x-filament::icon
                                    icon="heroicon-o-folder"
                                    class="equipment-set-group-handle-icon equipment-set-group-handle-icon--closed"
                                />
                                <x-filament::icon
                                    icon="heroicon-o-folder-open"
                                    class="equipment-set-group-handle-icon equipment-set-group-handle-icon--open"
                                />
                            </button>

                            <span>{{ $group->label }}</span>

                            <x-filament::icon-button
                                icon="heroicon-o-pencil-square"
                                :label="__('Edit group')"
                                color="gray"
                                size="sm"
                                wire:click="mountAction('editGroup', { group: '{{ $group->getKey() }}' })"
                            />
                        </div>
                    </x-slot>

                    <x-slot name="afterHeader">
                        <div class="flex flex-wrap items-center gap-1">
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

                    {{--
                        One Sortable per group: sort disabled, same-group moves rejected.
                        Ghost is hidden; is-drop-target highlights the whole group instead
                        of showing a between-row insert slot. onAdd places A–Z.
                    --}}
                    <div
                        class="equipment-set-drop-zone"
                        data-group-id="{{ $group->getKey() }}"
                        wire:key="equipment-drop-{{ $group->getKey() }}-{{ $group->equipmentSets->pluck('id')->join('-') }}"
                        x-data
                        x-init="
                            if (typeof Sortable === 'undefined') {
                                return;
                            }

                            const clearDropTargets = () => {
                                document
                                    .querySelectorAll('.equipment-set-drop-zone.is-drop-target')
                                    .forEach((zone) => zone.classList.remove('is-drop-target'));
                            };

                            // dragleave fires on the element being left (drop zone), not only the
                            // drag source. Ignore moves into children so the ring does not flicker.
                            $el.addEventListener('dragleave', (event) => {
                                if ($el.contains(event.relatedTarget)) {
                                    return;
                                }

                                $el.classList.remove('is-drop-target');
                            });

                            Sortable.create($el, {
                                group: 'equipment-sets',
                                sort: false,
                                animation: 150,
                                draggable: '[data-set-id]',
                                handle: '[data-set-handle]',
                                filter: '.invite-empty',
                                ghostClass: 'equipment-set-move-ghost',
                                chosenClass: 'equipment-set-move-chosen',
                                onMove(event) {
                                    const fromGroup = event.from.dataset.groupId;
                                    const toGroup = event.to.dataset.groupId;
                                    const allowed = Boolean(toGroup && fromGroup !== toGroup);

                                    clearDropTargets();

                                    if (allowed) {
                                        event.to.classList.add('is-drop-target');
                                    }

                                    return allowed;
                                },
                                onAdd(event) {
                                    clearDropTargets();

                                    const setId = event.item.dataset.setId;
                                    const groupId = $el.dataset.groupId;
                                    const name = (event.item.dataset.setName || '').toLocaleLowerCase();
                                    const siblings = [...$el.querySelectorAll(':scope > [data-set-id]')].filter(
                                        (node) => node !== event.item,
                                    );
                                    const next = siblings.find(
                                        (node) => (node.dataset.setName || '').toLocaleLowerCase() > name,
                                    );

                                    if (next) {
                                        $el.insertBefore(event.item, next);
                                    } else {
                                        $el.appendChild(event.item);
                                    }

                                    const empty = $el.querySelector('.invite-empty');
                                    if (empty) {
                                        empty.remove();
                                    }

                                    if (setId && groupId) {
                                        $wire.moveSetToGroup(setId, groupId);
                                    }
                                },
                                onEnd() {
                                    clearDropTargets();
                                },
                            });
                        "
                    >
                        @forelse ($group->equipmentSets as $set)
                            <div
                                class="invite-row"
                                data-set-id="{{ $set->getKey() }}"
                                data-set-name="{{ $set->name }}"
                                wire:key="equipment-set-{{ $set->getKey() }}"
                            >
                                <div class="invite-person">
                                    <x-filament::icon-button
                                        data-set-handle
                                        icon="heroicon-o-arrows-right-left"
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
