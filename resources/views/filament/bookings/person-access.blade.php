@if ($access)
    @php($alpine = $alpine ?? false)
    @php($framed = $framed ?? true)

    <div
        @class([
            'flex flex-col gap-3 text-sm',
            'rounded-xl border border-gray-200 p-4 dark:border-white/10' => ! $alpine && $framed,
        ])
        @if ($alpine) x-data="{ showOther: false, qualification: null }" @endif
    >
        @unless ($alpine)
            <div class="flex items-center justify-between gap-3">
                <h3 class="font-semibold text-gray-950 dark:text-white">{{ $access['name'] }}</h3>
                <x-filament::button
                    size="sm"
                    color="gray"
                    outlined
                    wire:click="{{ filled($access['detail'] ?? null) ? 'closeQualification' : 'closeAccess' }}"
                >
                    {{ filled($access['detail'] ?? null) ? __('Back') : ($dismiss ?? __('Close')) }}
                </x-filament::button>
            </div>
        @endunless

        @if ($access['panel'] === 'keys')
            @forelse ($access['rings'] as $ring)
                <section class="flex flex-col gap-1">
                    <h3 class="font-semibold text-gray-950 dark:text-white">{{ $ring['name'] }}</h3>
                    <ul class="list-disc ps-5 text-gray-700 dark:text-gray-200">
                        @foreach ($ring['keys'] as $key)
                            <li>{{ $key }}</li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <p class="text-gray-500 dark:text-gray-400">{{ __('No keys recorded.') }}</p>
            @endforelse
        @elseif ($alpine)
            <div x-show="qualification === null" class="flex flex-col gap-2">
                @forelse ($access['relevant'] as $qualification)
                    <x-filament::button
                        color="gray"
                        outlined
                        class="w-full justify-start"
                        data-qualification="{{ $qualification['id'] }}"
                        x-on:click="qualification = $el.dataset.qualification"
                    >
                        <span class="flex flex-col items-start gap-0.5 text-start">
                            <span>{{ $qualification['type'] }} — {{ $qualification['summary'] }}</span>
                            @if ($qualification['expires'])
                                <span class="text-xs font-normal {{ $qualification['expires_class'] }}">{{ __('Expires') }} {{ $qualification['expires'] }}</span>
                            @endif
                        </span>
                    </x-filament::button>
                @empty
                    <p class="text-gray-500 dark:text-gray-400">{{ __('No qualifications meet this activity.') }}</p>
                @endforelse

                @if ($access['other'] !== [])
                    <div class="border-t border-gray-200 pt-3 dark:border-white/10">
                        <x-filament::button color="gray" outlined size="sm" x-on:click="showOther = ! showOther">
                            <span x-show="! showOther">{{ __('Show other qualifications') }}</span>
                            <span x-show="showOther" x-cloak>{{ __('Hide other qualifications') }}</span>
                        </x-filament::button>
                    </div>

                    <div x-show="showOther" x-cloak class="flex flex-col gap-2">
                        @foreach ($access['other'] as $qualification)
                            <x-filament::button
                                color="gray"
                                outlined
                                class="w-full justify-start"
                                data-qualification="{{ $qualification['id'] }}"
                                x-on:click="qualification = $el.dataset.qualification"
                            >
                                <span class="flex flex-col items-start gap-0.5 text-start">
                                    <span>{{ $qualification['type'] }} — {{ $qualification['summary'] }}</span>
                                    @if ($qualification['expires'])
                                        <span class="text-xs font-normal {{ $qualification['expires_class'] }}">{{ __('Expires') }} {{ $qualification['expires'] }}</span>
                                    @endif
                                </span>
                            </x-filament::button>
                        @endforeach
                    </div>
                @endif
            </div>

            @foreach ([...$access['relevant'], ...$access['other']] as $qualification)
                <div x-show="qualification === $el.dataset.qualification" data-qualification="{{ $qualification['id'] }}" x-cloak class="flex flex-col gap-3">
                    <x-filament::button color="gray" outlined size="sm" class="self-start" x-on:click="qualification = null">
                        {{ __('Back') }}
                    </x-filament::button>

                    <div class="mb-2 border-b border-gray-200 pb-2 dark:border-white/10">
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $qualification['type'] }}</p>
                    </div>

                    <dl class="grid grid-cols-[9rem_minmax(0,1fr)] gap-x-3 gap-y-2">
                        @foreach ($qualification['fields'] as $field)
                            <dt class="text-gray-500 dark:text-gray-400">{{ $field['label'] }}</dt>
                            <dd class="text-gray-950 dark:text-white">{{ $field['value'] }}</dd>
                        @endforeach
                        @if ($qualification['expires'])
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Expires') }}</dt>
                            <dd class="{{ $qualification['expires_class'] }}">{{ $qualification['expires'] }}</dd>
                        @endif
                    </dl>
                </div>
            @endforeach
        @elseif ($access['detail'])
            <div class="border size-md color-gray rounded-xl p-4 w-full">
                <div class="mb-2 border-b border-gray-200 pb-2 dark:border-white/10">
                    <p class="font-semibold text-gray-950 dark:text-white">{{ $access['detail']['type'] }}</p>
                </div>
                <dl class="grid grid-cols-[9rem_minmax(0,1fr)] gap-x-3 gap-y-2">
                    @foreach ($access['detail']['fields'] as $field)
                        <dt class="text-gray-500 dark:text-gray-400">{{ $field['label'] }}</dt>
                        <dd class="text-gray-950 dark:text-white">{{ $field['value'] }}</dd>
                    @endforeach
                    @if ($access['detail']['expires'])
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('Expires') }}</dt>
                        <dd class="{{ $access['detail']['expires_class'] }}">{{ $access['detail']['expires'] }}</dd>
                    @endif
                </dl>
            </div>
        @else
            <div class="flex flex-col gap-2">
                @forelse ($access['relevant'] as $qualification)
                    <x-filament::button
                        color="gray"
                        outlined
                        class="w-full justify-start"
                        wire:click="openQualification('{{ $qualification['id'] }}')"
                    >
                        <span class="flex flex-col items-start gap-0.5 text-start">
                            <span>{{ $qualification['type'] }} — {{ $qualification['summary'] }}</span>
                            @if ($qualification['expires'])
                                <span class="text-xs font-normal {{ $qualification['expires_class'] }}">{{ __('Expires') }} {{ $qualification['expires'] }}</span>
                            @endif
                        </span>
                    </x-filament::button>
                @empty
                    <p class="text-gray-500 dark:text-gray-400">{{ __('No qualifications meet this activity.') }}</p>
                @endforelse
            </div>

            @if ($access['has_other'])
                <div class="border-t border-gray-200 pt-3 dark:border-white/10">
                    <x-filament::button size="sm" color="gray" outlined wire:click="toggleOtherQualifications">
                        {{ $access['show_other'] ? __('Hide other qualifications') : __('Show other qualifications') }}
                    </x-filament::button>
                </div>
            @endif

            @if ($access['other'] !== [])
                <div class="flex flex-col gap-2">
                    @foreach ($access['other'] as $qualification)
                        <x-filament::button
                            color="gray"
                            outlined
                            class="w-full justify-start"
                            wire:click="openQualification('{{ $qualification['id'] }}')"
                        >
                            <span class="flex flex-col items-start gap-0.5 text-start">
                                <span>{{ $qualification['type'] }} — {{ $qualification['summary'] }}</span>
                                @if ($qualification['expires'])
                                    <span class="text-xs font-normal {{ $qualification['expires_class'] }}">{{ __('Expires') }} {{ $qualification['expires'] }}</span>
                                @endif
                            </span>
                        </x-filament::button>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endif
