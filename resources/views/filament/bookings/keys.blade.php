<div class="flex flex-col gap-6">
    @forelse ($keys as $key)
        <section class="flex flex-col gap-3">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $key['name'] }}</h3>

            @foreach ($key['groups'] as $group)
                @php($situation = $group['situation'])
                <div class="flex flex-col gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::badge :color="$situation->getColor()" :icon="$situation->getIcon()">
                            {{ $situation->getLabel() }}
                        </x-filament::badge>
                        @if (filled($situation->helper()))
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $situation->helper() }}</p>
                        @endif
                    </div>
                    <ul class="list-disc ps-5 text-sm text-gray-700 dark:text-gray-200">
                        @foreach ($group['rings'] as $ring)
                            <li>
                                {{ $ring['name'] }}
                                @if (filled($ring['holder']))
                                    —
                                    @if (filled($ring['url']))
                                        <a href="{{ $ring['url'] }}"
                                            class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $ring['holder'] }}</a>
                                    @else
                                        {{ $ring['holder'] }}
                                    @endif
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </section>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No keys required.') }}</p>
    @endforelse
</div>
