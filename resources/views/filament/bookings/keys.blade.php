<div>
    <div class="flex flex-col gap-6">
        @forelse ($groups as $group)
            @php
                $situation = $group['situation'];
            @endphp
            <section class="flex flex-col gap-2" wire:key="keys-group-{{ $situation->value }}">
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::badge :color="$situation->getColor()" :icon="$situation->getIcon()">
                        {{ $situation->getLabel() }}
                    </x-filament::badge>
                    @if (filled($situation->helper()))
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $situation->helper() }}</p>
                    @endif
                </div>

                <div class="invite-list">
                    @foreach ($group['people'] as $person)
                        @php
                            $canContact = $person['can_contact'] ?? false;
                            $hasContactDetails = $person['has_contact_details'] ?? false;
                            $canInvite = $person['can_invite'] ?? false;
                            $canEdit = $person['can_edit'] ?? false;
                            $canViewMember = $person['can_view_member'] ?? false;
                            $showManagerMenu = $canEdit || $canViewMember;
                            $contactIcon = $hasContactDetails
                                ? \Filament\Support\Icons\Heroicon::OutlinedPhoneArrowUpRight
                                : \Filament\Support\Icons\Heroicon::OutlinedPhoneXMark;
                        @endphp

                        <div class="invite-row" wire:key="keys-person-{{ $situation->value }}-{{ $person['id'] }}">
                            <div class="invite-person">
                                @include('filament.bookings.person-identity', ['person' => $person])

                                @foreach ($person['rings'] as $ring)
                                    @php
                                        $ringLabel = $ring['locks'] === []
                                            ? $ring['name']
                                            : $ring['name'].' · '.implode(', ', $ring['locks']);
                                    @endphp
                                    <x-filament::badge :color="\App\Support\Palette::neutral()">
                                        {{ $ringLabel }}
                                    </x-filament::badge>
                                @endforeach
                            </div>

                            @if ($canContact || $canInvite || $showManagerMenu)
                                <div class="flex items-center gap-1">
                                    @if ($canContact)
                                        <x-filament::icon-button
                                            color="gray"
                                            size="sm"
                                            :icon="$contactIcon"
                                            :label="__('Contact')"
                                            wire:click="mountAction('contact', { person: '{{ $person['id'] }}' })"
                                        />
                                    @endif

                                    @if ($canInvite)
                                        <x-filament::icon-button
                                            color="gray"
                                            size="sm"
                                            :icon="\Filament\Support\Icons\Heroicon::OutlinedPaperAirplane"
                                            :label="__('Invite')"
                                            wire:click="mountAction('inviteHolder', { person: '{{ $person['id'] }}' })"
                                        />
                                    @endif

                                    @if ($showManagerMenu)
                                        <x-filament::dropdown placement="bottom-end"
                                            wire:key="keys-menu-{{ $person['id'] }}">
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
                                                        wire:click="mountAction('editAttendance', { person: '{{ $person['id'] }}' })">
                                                        {{ __('Edit') }}
                                                    </x-filament::dropdown.list.item>
                                                @endif

                                                @if ($canViewMember && filled($person['view_member_url'] ?? null))
                                                    <x-filament::dropdown.list.item
                                                        tag="a"
                                                        :href="$person['view_member_url']"
                                                        :icon="\Filament\Support\Icons\Heroicon::OutlinedUser">
                                                        {{ __('View member') }}
                                                    </x-filament::dropdown.list.item>
                                                @endif
                                            </x-filament::dropdown.list>
                                        </x-filament::dropdown>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @foreach ($group['rings'] as $ring)
                        @php
                            $ringLabel = $ring['locks'] === []
                                ? $ring['name']
                                : $ring['name'].' · '.implode(', ', $ring['locks']);
                        @endphp
                        <div class="invite-row" wire:key="keys-ring-{{ $situation->value }}-{{ $ring['name'] }}">
                            <div class="invite-person">
                                <x-filament::badge :color="\App\Support\Palette::neutral()">
                                    {{ $ringLabel }}
                                </x-filament::badge>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No keys required.') }}</p>
        @endforelse
    </div>

    <x-filament-actions::modals />
</div>
