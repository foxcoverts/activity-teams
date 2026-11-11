@php
    $previewLimit = \App\Support\MemberDashboard::PREVIEW_LIMIT;
    $showTeam ??= true;
    $tasksTotal = $overdueTotal + $assignedTotal;
    $tasksShown = $overdue->count() + $assigned->count();
@endphp
<x-filament-widgets::widget :class="!$failed && ($overdue->isEmpty() && $assigned->isEmpty()) ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif (!($overdue->isEmpty() && $assigned->isEmpty()))
        <x-filament::section :heading="__('Your tasks')" :icon="\Filament\Support\Icons\Heroicon::OutlinedClipboardDocumentList">
            @if ($overdue->isNotEmpty())
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    {{ __('Overdue') }}
                    <x-filament::badge size="sm" :color="\App\Support\Palette::destructive()">{{ $overdueTotal }}</x-filament::badge>
                </h3>
                <ul class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($overdue as $task)
                        @include('filament.member.widgets.task-row', [
                            'task' => $task,
                            'showTeam' => $showTeam,
                            'canToggle' => ($canToggle)($task),
                            'viewUrl' => ($viewUrl)($task),
                        ])
                    @endforeach
                </ul>
            @endif

            @if ($assigned->isNotEmpty())
                <h3
                    class="@if ($overdue->isNotEmpty()) mt-8 @endif mb-3 flex items-center gap-2 text-sm font-semibold">
                    {{ __('Assigned') }}
                    <x-filament::badge size="sm" :color="\App\Support\Palette::neutral()">{{ $assignedTotal }}</x-filament::badge>
                </h3>
                <ul class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($assigned as $task)
                        @include('filament.member.widgets.task-row', [
                            'task' => $task,
                            'showTeam' => $showTeam,
                            'canToggle' => ($canToggle)($task),
                            'viewUrl' => ($viewUrl)($task),
                        ])
                    @endforeach
                </ul>
            @endif

            @if ($tasksTotal > $tasksShown)
                <div
                    class="mt-4 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
                    <x-filament::link :href="$tasksUrl" :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowRight"
                        icon-position="after">{{ __('All tasks') }}</x-filament::link>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
