<x-filament-widgets::widget :class="!$failed && $rows->isEmpty() ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif ($rows->isNotEmpty())
        <x-filament::section :heading="$heading" :icon="$icon" :icon-color="$iconColor">
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($rows as $record)
                    <li class="relative py-4 first:pt-0 last:pb-0"
                        wire:key="{{ $rowKeyPrefix }}-{{ $record->getKey() }}">
                        <p class="text-base font-semibold">
                            <a href="{{ ($recordUrl)($record) }}"
                                class="hover:underline after:absolute after:inset-0 after:z-0 after:content-['']">
                                {{ $record->name }}
                            </a>
                        </p>
                    </li>
                @endforeach
            </ul>

            @if ($total > $previewLimit)
                <x-filament::link :href="$seeAllUrl" class="mt-3">{{ $seeAllLabel }}</x-filament::link>
            @endif
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
