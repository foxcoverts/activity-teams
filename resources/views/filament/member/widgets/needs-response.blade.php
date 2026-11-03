@php($previewLimit = \App\Support\MemberDashboard::PREVIEW_LIMIT)
<x-filament-widgets::widget :class="(! $failed && ($awaiting->isEmpty() && $maybe->isEmpty())) ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif (! ($awaiting->isEmpty() && $maybe->isEmpty()))
        <x-filament::section :heading="__('Needs your response')" :icon="\Filament\Support\Icons\Heroicon::OutlinedQuestionMarkCircle">
            @if ($awaiting->isNotEmpty())
                <h3 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    {{ __('Awaiting response') }}
                    <x-filament::badge size="sm" :color="\App\Support\Palette::neutral()">{{ $awaitingTotal }}</x-filament::badge>
                </h3>
                @include('filament.member.widgets.booking-days', ['bookings' => $awaiting, 'showResponse' => true, 'showStatus' => false])
                @if ($awaitingTotal > $previewLimit)
                    <x-filament::link :href="$invitesUrl" class="mt-2">{{ __('See all :count invites', ['count' => $awaitingTotal]) }}</x-filament::link>
                @endif
            @endif

            @if ($maybe->isNotEmpty())
                <h3 class="@if ($awaiting->isNotEmpty()) mt-8 @endif mb-3 flex items-center gap-2 text-sm font-semibold">
                    {{ __('Maybe') }}
                    <x-filament::badge size="sm" :color="\App\Support\Palette::uncertain()">{{ $maybeTotal }}</x-filament::badge>
                </h3>
                @include('filament.member.widgets.booking-days', ['bookings' => $maybe, 'showResponse' => true, 'showStatus' => false])
                @if ($maybeTotal > $previewLimit)
                    <x-filament::link :href="$invitesUrl" class="mt-2">{{ __('See all invites') }}</x-filament::link>
                @endif
            @endif
        </x-filament::section>
        <x-filament-actions::modals />
    @endif
</x-filament-widgets::widget>
