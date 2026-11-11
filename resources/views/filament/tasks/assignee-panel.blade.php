<div>
    @if ($showPanel)
        <x-filament::section
            class="task-assignee-panel"
            :heading="__('Assignee')"
            :icon="\Filament\Support\Icons\Heroicon::OutlinedUser"
            :has-content-el="false"
        >
            @if ($showTools)
                <x-slot name="afterHeader">
                    {{ $this->assigneeGroup() }}
                </x-slot>
            @endif

            @if ($assignee === null)
                <p class="invite-empty">{{ __('No assignee.') }}</p>
            @else
                <div @class(['invite-row', 'relative' => filled($memberUrl)])>
                    <div class="invite-person">
                        <x-filament::avatar
                            :src="$assignee->getFilamentAvatarUrl()"
                            :alt="$assignee->name"
                            size="md"
                            class="shrink-0"
                        />
                        <div class="min-w-0">
                            @if ($memberUrl)
                                <a
                                    href="{{ $memberUrl }}"
                                    class="block truncate text-sm font-medium text-gray-950 hover:underline after:absolute after:inset-0 after:z-0 after:content-[''] dark:text-white"
                                >
                                    {{ $assignee->name }}
                                </a>
                            @else
                                <div class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $assignee->name }}
                                </div>
                            @endif
                            @if ($attendance !== null)
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $attendance->status->getLabel() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::section>

        <x-filament-actions::modals />
    @endif
</div>
