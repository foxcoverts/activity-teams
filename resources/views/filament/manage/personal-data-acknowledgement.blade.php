<div class="space-y-4 text-sm">
    <p>
        <strong>{{ __('Notice:') }}</strong>
        {{ __('You may only use these details to contact team members regarding legitimate team matters. Any other use of these contact details, no matter how well intended, will be in breach of UK data protection laws.') }}
    </p>

    <label class="flex items-center gap-2">
        <x-filament::input.checkbox wire:change="acknowledgePersonalData('{{ $section->value }}')" />
        <span>{{ __('I have a legitimate reason to view these details') }}</span>
    </label>
</div>
