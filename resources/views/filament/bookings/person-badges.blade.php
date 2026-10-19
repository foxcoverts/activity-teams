@if ($showQualified && $person['qualified'])
    <button type="button" class="invite-badge" wire:click="openQualifications('{{ $person['id'] }}')">
        <x-filament::badge :color="\App\Support\Palette::positive()" :icon="\Filament\Support\Icons\Heroicon::CheckCircle">
            {{ __('Qualified') }}
        </x-filament::badge>
    </button>
@endif

@if ($showKeys && $person['key_badge'] === 'holder')
    <button type="button" class="invite-badge" wire:click="openKeys('{{ $person['id'] }}')">
        <x-filament::badge :color="\App\Support\Palette::positive()" :icon="\Filament\Support\Icons\Heroicon::OutlinedKey">
            {{ __('Key holder') }}
        </x-filament::badge>
    </button>
@endif

@if ($showKeys && $person['key_badge'] === 'other')
    <button type="button" class="invite-badge" wire:click="openKeys('{{ $person['id'] }}')">
        <x-filament::badge :color="\App\Support\Palette::neutral()" :icon="\Filament\Support\Icons\Heroicon::OutlinedKey">
            {{ __('Other keys') }}
        </x-filament::badge>
    </button>
@endif
