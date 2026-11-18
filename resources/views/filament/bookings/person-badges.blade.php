@php
    $panel = $panel ?? 'invite';
    $guestListPanel = in_array($panel, ['teams', 'member-relation-manager'], true);
@endphp

@if ($panel === 'invite' && ($person['mail_off'] ?? false))
    <x-filament::badge :color="\App\Support\Palette::uncertain()" :icon="\Filament\Support\Icons\Heroicon::OutlinedBellSlash" :tooltip="__('Booking emails are turned off')">
        {{ __('No email') }}
    </x-filament::badge>
@endif

@if ($person['under_18'])
    <x-filament::badge :color="\App\Support\Palette::youth()">
        {{ __('Under 18') }}
    </x-filament::badge>
@endif

@if ($person['is_parent'] && !$person['qualified'])
    <x-filament::badge :color="\App\Enums\MembershipRole::Parent->getColor()" :icon="\App\Enums\MembershipRole::Parent->getIcon()">
        {{ __('Parent') }}
    </x-filament::badge>
@endif

@if ($showQualified && $person['qualified'])
    @if ($guestListPanel)
        <x-filament::icon-button
            :color="\App\Support\Palette::positive()"
            size="sm"
            :icon="\App\Filament\Member\Resources\Qualifications\QualificationResource::ICON"
            :label="__('Qualified')"
            :tooltip="__('Qualified')"
            wire:click.stop="mountTableAction('personDetails', '{{ $person['id'] }}', { tab: 'qualifications' })"
        />
    @else
        <x-filament::icon-button
            :color="\App\Support\Palette::positive()"
            size="sm"
            :icon="\App\Filament\Member\Resources\Qualifications\QualificationResource::ICON"
            :label="__('Qualified')"
            :tooltip="__('Qualified')"
            wire:click="openQualifications('{{ $person['id'] }}')"
        />
    @endif
@endif

@if ($showKeys && $person['key_badge'] === 'holder')
    @if ($guestListPanel)
        <x-filament::icon-button
            :color="\App\Support\Palette::positive()"
            size="sm"
            :icon="\Filament\Support\Icons\Heroicon::OutlinedKey"
            :label="__('Key holder')"
            :tooltip="__('Key holder')"
            wire:click.stop="mountTableAction('personDetails', '{{ $person['id'] }}', { tab: 'keys' })"
        />
    @else
        <x-filament::icon-button
            :color="\App\Support\Palette::positive()"
            size="sm"
            :icon="\Filament\Support\Icons\Heroicon::OutlinedKey"
            :label="__('Key holder')"
            :tooltip="__('Key holder')"
            wire:click="openKeys('{{ $person['id'] }}')"
        />
    @endif
@endif
