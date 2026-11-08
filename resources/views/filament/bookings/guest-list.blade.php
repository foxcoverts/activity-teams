<section class="invite-block">
    @if ($showHeading)
        <h3 class="invite-heading">{{ __('Guest list') }}</h3>
    @endif

    @forelse ($groups as $group)
        <div class="invite-list guest-roster-group" wire:key="guest-group-{{ $group['key'] }}" x-data="{ expanded: @js($group['open']) }">
            <button type="button" class="invite-group-heading" x-on:click="expanded = ! expanded"
                x-bind:aria-expanded="expanded">
                <span class="flex items-center gap-2">
                    <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::ChevronRight" class="size-4 shrink-0 transition-transform"
                        x-bind:class="{ 'rotate-90': expanded }" />
                    <span>{{ $group['label'] }}</span>
                </span>
                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ count($group['people']) }}</span>
            </button>

            <div x-show="expanded" x-cloak>
                @foreach ($group['people'] as $person)
                    <div class="invite-row" wire:key="guest-{{ $person['id'] }}">
                        <div class="invite-person">
                            @include('filament.bookings.person-identity', ['person' => $person])

                            @if ($person['guest'])
                                <x-filament::badge class="guest-badge" :color="\App\Support\Palette::neutral()">
                                    {{ __('Guest') }}
                                </x-filament::badge>
                            @endif

                            @include('filament.bookings.person-badges', [
                                'person' => $person,
                                'showQualified' => $showAccessDetails && $showQualified,
                                'showKeys' => $showAccessDetails && $showKeys,
                                'panel' => $panel,
                            ])
                        </div>

                        @php
                            $canContact = ($person['can_phone'] ?? false) || ($person['can_emergency'] ?? false);
                            $hasContactDetails = $person['has_contact_details'] ?? false;
                            $canEdit = $person['can_edit'] ?? false;
                            $canRemove = $person['can_remove'] ?? false;
                            $showManagerMenu = $panel === 'teams' && ($canEdit || $canRemove);
                        @endphp

                        @if ($canContact || $showManagerMenu)
                            <div class="flex items-center gap-1">
                                @if ($canContact)
                                    <x-filament::icon-button
                                        color="gray"
                                        size="sm"
                                        :icon="$hasContactDetails
                                            ? \Filament\Support\Icons\Heroicon::OutlinedPhoneArrowUpRight
                                            : \Filament\Support\Icons\Heroicon::OutlinedPhoneXMark"
                                        :label="__('Contact')"
                                        wire:click="mountTableAction('contact', '{{ $person['id'] }}')"
                                    />
                                @endif

                                @if ($showManagerMenu)
                                    <x-filament::dropdown placement="bottom-end"
                                        wire:key="guest-menu-{{ $person['id'] }}">
                                        <x-slot name="trigger">
                                            <x-filament::icon-button
                                                color="gray"
                                                size="sm"
                                                :icon="\Filament\Support\Icons\Heroicon::OutlinedEllipsisHorizontal"
                                                :label="__('More actions')"
                                            />
                                        </x-slot>

                                        <x-filament::dropdown.list>
                                            @if ($canEdit)
                                                <x-filament::dropdown.list.item
                                                    :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare"
                                                    wire:click="mountTableAction('editAttendance', '{{ $person['id'] }}')">
                                                    {{ __('Edit') }}
                                                </x-filament::dropdown.list.item>
                                            @endif

                                            @if ($canRemove)
                                                <x-filament::dropdown.list.item
                                                    color="danger"
                                                    :icon="\Filament\Support\Icons\Heroicon::OutlinedTrash"
                                                    wire:click="mountTableAction('detach', '{{ $person['id'] }}')">
                                                    {{ __('Remove') }}
                                                </x-filament::dropdown.list.item>
                                            @endif
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <p class="invite-empty">{{ __('No one is on this session.') }}</p>
    @endforelse
</section>
