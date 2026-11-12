<x-filament-widgets::widget>
    @if ($failed)
        @include('filament.member.widgets.failed')
    @else
        <x-filament::section :heading="$heading" :icon="$icon" :icon-color="$iconColor">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ __('You are not a member of :team, so the personal overview (this week, responses, and tasks) is hidden.', ['team' => $teamName]) }}
            </p>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Use the team lists to inspect this workspace, or open Manager for tentative and bounce triage.') }}
            </p>
            <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2">
                @if (! empty($managerUrl))
                    <x-filament::link :href="$managerUrl">{{ __('Open Manager') }}</x-filament::link>
                @endif
                @if (! empty($membersUrl))
                    <x-filament::link :href="$membersUrl">{{ __('Browse members') }}</x-filament::link>
                @endif
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
