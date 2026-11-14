@php($previewLimit = \App\Support\MemberDashboard::PREVIEW_LIMIT)
<x-filament-widgets::widget :class="!$failed && $rows->isEmpty() ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif ($rows->isNotEmpty())
        <x-filament::section :heading="$heading" :icon="$icon" :icon-color="$iconColor">
            @include('filament.member.widgets.booking-days', [
                'bookings' => $rows,
                'showResponse' => false,
                'showStatus' => true,
            ])
            @if ($total > $previewLimit)
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('Showing :shown of :total debriefs due.', [
                        'shown' => $previewLimit,
                        'total' => $total,
                    ]) }}
                </p>
            @endif
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
