<div class="invite-panel">
    @if ($access)
        @include('filament.bookings.person-access', [
            'access' => $access,
            'framed' => false,
            'dismiss' => __('Back'),
        ])
    @else
        <section class="invite-block">
            <h3 class="invite-heading">{{ __('To be invited') }}</h3>

            <div class="invite-list">
                @forelse ($queuedPeople as $person)
                    <div class="invite-row" wire:key="queued-{{ $person['id'] }}">
                        <div class="invite-person">
                            <span>{{ $person['name'] }}</span>
                            @include('filament.bookings.person-badges', [
                                'person' => $person,
                                'showQualified' => $showQualified,
                                'showKeys' => $showKeys,
                            ])
                        </div>

                        <x-filament::button size="sm" color="gray" outlined :icon="\Filament\Support\Icons\Heroicon::XMark"
                            wire:click="remove('{{ $person['id'] }}')">
                            {{ __('Remove') }}
                        </x-filament::button>
                    </div>
                @empty
                    <p class="invite-empty">{{ __('No one is queued.') }}</p>
                @endforelse
            </div>
        </section>

        <section class="invite-block">
            <h3 class="invite-heading">{{ __('Team') }}</h3>

            <div class="invite-toolbar">
                <div class="invite-filters">
                    <div class="invite-name">
                        <x-filament::input.wrapper :prefix-icon="\Filament\Support\Icons\Heroicon::MagnifyingGlass">
                            <x-filament::input type="search" wire:model.live.debounce.300ms="name" :placeholder="__('Name')" />
                        </x-filament::input.wrapper>
                    </div>

                    @if ($showQualifiedFilter)
                        <x-filament::button size="sm" :color="$qualifiedOnly ? 'primary' : 'gray'" :outlined="!$qualifiedOnly"
                            wire:click="toggleQualified">
                            {{ __('Qualified only') }}
                        </x-filament::button>
                    @endif

                    @if ($showKeyFilter)
                        <x-filament::button size="sm" :color="$keyHoldersOnly ? 'primary' : 'gray'" :outlined="!$keyHoldersOnly"
                            wire:click="toggleKeyHolders">
                            {{ __('Key holders only') }}
                        </x-filament::button>
                    @endif

                    <x-filament::button size="sm" :color="$membersOnly ? 'primary' : 'gray'" :outlined="!$membersOnly"
                        wire:click="toggleMembersOnly">
                        {{ __('Members only') }}
                    </x-filament::button>
                </div>

                <x-filament::button size="sm" color="gray" outlined wire:click="addShown" :disabled="!$canAddShown">
                    {{ __('Add everyone shown') }}
                </x-filament::button>
            </div>

            <div class="invite-list">
                @forelse ($pickerPeople as $person)
                    <div class="invite-row" wire:key="picker-{{ $person['id'] }}">
                        <div class="invite-person">
                            <span>{{ $person['name'] }}</span>
                            @include('filament.bookings.person-badges', [
                                'person' => $person,
                                'showQualified' => $showQualified,
                                'showKeys' => $showKeys,
                            ])
                            @if ($person['status'])
                                <x-filament::badge :color="$person['status']->getColor()" :icon="$person['status']->getIcon()">
                                    {{ $person['status']->getLabel() }}
                                </x-filament::badge>
                            @endif
                        </div>

                        @if ($person['can_add'])
                            <x-filament::button size="sm" color="gray" outlined :icon="\Filament\Support\Icons\Heroicon::Plus"
                                wire:click="add('{{ $person['id'] }}')">
                                {{ __('Add') }}
                            </x-filament::button>
                        @endif
                    </div>
                @empty
                    <p class="invite-empty">{{ __('No one else to show.') }}</p>
                @endforelse
            </div>
        </section>

        <div class="invite-footer">
            <x-filament::button color="gray" wire:click="cancel">
                {{ __('Cancel') }}
            </x-filament::button>

            <x-filament::button wire:click="send" :disabled="$queuedPeople === []">
                {{ __('Send invites') }}
            </x-filament::button>
        </div>
    @endif
</div>
