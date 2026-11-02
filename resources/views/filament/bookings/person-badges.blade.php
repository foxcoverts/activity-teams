@php($panel = $panel ?? 'invite')

@if ($panel === 'invite' && ($person['mail_off'] ?? false))
    <x-filament::badge
        :color="\App\Support\Palette::uncertain()"
        :icon="\Filament\Support\Icons\Heroicon::OutlinedBellSlash"
        :tooltip="__('Booking emails are turned off')"
    >
        {{ __('No email') }}
    </x-filament::badge>
@endif

@if ($person['under_18'])
    <x-filament::badge :color="\App\Support\Palette::youth()">
        {{ __('Under 18') }}
    </x-filament::badge>
@endif

@if ($person['is_parent'] && ! $person['qualified'])
    <x-filament::badge
        :color="\App\Enums\MembershipRole::Parent->getColor()"
        :icon="\App\Enums\MembershipRole::Parent->getIcon()"
    >
        {{ __('Parent') }}
    </x-filament::badge>
@endif

@if ($showQualified && $person['qualified'])
    <button
        type="button"
        class="invite-badge"
        wire:click="{{ in_array($panel, ['manage', 'member-relation-manager'], true)
            ? "mountTableAction('qualifications', '{$person['id']}')"
            : "openQualifications('{$person['id']}')" }}"
    >
        <x-filament::badge :color="\App\Support\Palette::positive()" :icon="\Filament\Support\Icons\Heroicon::CheckCircle">
            {{ __('Qualified') }}
        </x-filament::badge>
    </button>
@endif

@if ($panel === 'invite' && $showKeys && $person['key_badge'] === 'other')
    <button type="button" class="invite-badge" wire:click="openKeys('{{ $person['id'] }}')">
        <x-filament::badge :color="\App\Support\Palette::neutral()" :icon="\Filament\Support\Icons\Heroicon::OutlinedKey">
            {{ __('Other keys') }}
        </x-filament::badge>
    </button>
@endif

@if ($showKeys && $person['key_badge'] === 'holder')
    <button
        type="button"
        class="invite-badge"
        title="{{ __('Key holder') }}"
        aria-label="{{ __('Key holder') }}"
        wire:click="{{ in_array($panel, ['manage', 'member-relation-manager'], true)
            ? "mountTableAction('heldKeys', '{$person['id']}')"
            : "openKeys('{$person['id']}')" }}"
    >
        <x-filament::badge class="key-holder-badge" :color="\App\Support\Palette::positive()" :icon="\Filament\Support\Icons\Heroicon::OutlinedKey">
        </x-filament::badge>
    </button>
@endif
