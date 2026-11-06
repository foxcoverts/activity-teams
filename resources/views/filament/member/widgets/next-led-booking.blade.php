<x-filament-widgets::widget :class="!$failed && $rows->isEmpty() ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif (!$rows->isEmpty())
        <x-filament::section :heading="__('Next booking you lead')" :icon="\Filament\Support\Icons\Heroicon::OutlinedStar">
            @include('filament.member.widgets.booking-days', [
                'bookings' => $rows,
                'showStatus' => false,
                'warnings' => $warnings,
            ])
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
