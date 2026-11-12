@php
    use App\Filament\Teams\Resources\Members\MemberResource;
    use Filament\Support\Icons\Heroicon;
@endphp

<x-filament-widgets::widget :class="!$failed && $rows->isEmpty() ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif ($rows->isNotEmpty())
        <x-filament::section :heading="$heading" :icon="$icon" :icon-color="$iconColor">
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($rows as $member)
                    @php
                        $bouncedAt = $member->email_bounced_at;
                        $age = $bouncedAt?->diffForHumans();
                        $category = $member->email_bounce_category;
                        $chip =
                            'inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-sm text-gray-700 dark:bg-white/10 dark:text-gray-300';
                    @endphp
                    <li class="relative flex flex-col gap-2 py-4 first:pt-0 last:pb-0"
                        wire:key="bounce-{{ $member->getKey() }}">
                        <p class="text-base font-semibold">
                            <a href="{{ MemberResource::getUrl('view', ['record' => $member]) }}"
                                class="hover:underline after:absolute after:inset-0 after:z-0 after:content-['']">{{ $member->name }}</a>
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($age)
                                <span class="{{ $chip }}"><x-filament::icon :icon="Heroicon::OutlinedClock"
                                        class="size-4" />{{ __('Bounced :age', ['age' => $age]) }}</span>
                            @endif
                            @if (filled($category))
                                <span class="{{ $chip }}">{{ $category }}</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($total > $previewLimit)
                <x-filament::link :href="$seeAllUrl" class="mt-3">{{ $seeAllLabel }}</x-filament::link>
            @endif
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
