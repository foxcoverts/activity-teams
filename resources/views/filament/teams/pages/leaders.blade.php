<x-filament-panels::page>
    @if ($this->leaders()->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('This team has no leaders yet.') }}</p>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @foreach ($this->leaders() as $leader)
                <x-filament::section :key="$leader->getKey()">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <x-filament::avatar :src="$leader->getFilamentAvatarUrl()" :alt="$leader->name" size="lg" />
                            <div class="flex flex-col items-start gap-1">
                                <span
                                    class="text-base font-semibold text-gray-950 dark:text-white">{{ $leader->name }}</span>
                                <x-filament::badge :color="\App\Enums\MembershipRole::Manager->getColor()" :icon="\App\Enums\MembershipRole::Manager->getIcon()">
                                    {{ \App\Enums\MembershipRole::Manager->getLabel() }}
                                </x-filament::badge>
                            </div>
                        </div>

                        {{ ($this->contactAction)(['leader' => $leader->getKey()]) }}
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
