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
                            <span>{{ $person['name'] }}</span>

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

                        @if ($panel === 'member-relation-manager' && (($person['can_phone'] ?? false) || ($person['can_emergency'] ?? false)))
                            <div class="flex items-center gap-2">
                                @if ($person['can_phone'])
                                    <x-filament::button size="sm" color="gray" outlined
                                        wire:click="mountTableAction('contactPhone', '{{ $person['id'] }}')">
                                        {{ __('Phone') }}
                                    </x-filament::button>
                                @endif

                                @if ($person['can_emergency'])
                                    <x-filament::button size="sm" color="gray" outlined
                                        wire:click="mountTableAction('emergencyContact', '{{ $person['id'] }}')">
                                        {{ __('Emergency') }}
                                    </x-filament::button>
                                @endif
                            </div>
                        @endif

                        @if ($panel === 'manage')
                            <div class="flex items-center gap-2">
                                @if ($person['can_edit'])
                                    <x-filament::button size="sm" color="gray" outlined
                                        wire:click="mountTableAction('editAttendance', '{{ $person['id'] }}')">
                                        {{ __('Edit') }}
                                    </x-filament::button>
                                @endif

                                @if ($person['can_remove'])
                                    <x-filament::button size="sm" color="danger" outlined
                                        wire:click="mountTableAction('detach', '{{ $person['id'] }}')">
                                        {{ __('Remove') }}
                                    </x-filament::button>
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
