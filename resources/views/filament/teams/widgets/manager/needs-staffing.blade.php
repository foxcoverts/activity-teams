<x-filament-widgets::widget :class="!$failed && $rows->isEmpty() ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif ($rows->isNotEmpty())
        <x-filament::section :heading="$heading" :icon="$icon" :icon-color="$iconColor">
            @include('filament.teams.widgets.manager.booking-days', [
                'bookings' => $rows,
                'attention' => $attention,
            ])
            @if ($total > $previewLimit)
                <x-filament::link :href="$seeAllUrl" class="mt-3">{{ $seeAllLabel }}</x-filament::link>
            @endif
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
