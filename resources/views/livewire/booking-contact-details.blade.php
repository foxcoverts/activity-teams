<div class="space-y-4 text-sm">
    @if (!$allowed)
        <p class="text-gray-500 dark:text-gray-400">{{ __('These details are not available.') }}</p>
    @elseif (!$acknowledged)
        <p>
            <strong>{{ __('Notice:') }}</strong>
            {{ __('You may only use these details to contact team members regarding legitimate team matters. Any other use of these contact details, no matter how well intended, will be in breach of UK data protection laws.') }}
        </p>

        <label class="flex items-center gap-2">
            <x-filament::input.checkbox wire:change="acknowledge" />
            <span>{{ __('I have a legitimate reason to view these details') }}</span>
        </label>
    @else
        <dl class="flex flex-col gap-4">
            @foreach ($details as $detail)
                <div class="flex flex-col gap-1">
                    <dt class="font-medium text-gray-950 dark:text-white">{{ $detail['label'] }}</dt>
                    <dd class="text-gray-700 dark:text-gray-200">
                        @if (blank($detail['value']))
                            {{ __('Not provided') }}
                        @elseif ($detail['tel'])
                            <x-filament::link :href="'tel:' . preg_replace('/[^\d+]/', '', $detail['value'])" :icon="\Filament\Support\Icons\Heroicon::Phone">
                                {{ $detail['value'] }}
                            </x-filament::link>
                        @else
                            {{ $detail['value'] }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    @endif
</div>
