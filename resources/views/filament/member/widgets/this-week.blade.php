@php($previewLimit = \App\Support\MemberDashboard::PREVIEW_LIMIT)
<x-filament-widgets::widget :class="(! $failed && ($rows->isEmpty())) ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif (! ($rows->isEmpty()))
        <x-filament::section :heading="__('This week')" :icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays">
            @include('filament.member.widgets.booking-days', ['bookings' => $rows, 'showResponse' => true])
            @if ($total > $previewLimit)
                <x-filament::link :href="$rotaUrl" class="mt-3">{{ __('See all :count on your rota', ['count' => $total]) }}</x-filament::link>
            @endif
        </x-filament::section>
        <x-filament-actions::modals />
    @endif
</x-filament-widgets::widget>
