@php use RalphJSmit\Filament\Activitylog\Filament\Infolists\Components\Timeline\ActivityTimelineItem; @endphp
@props(['timelineData', 'parentActivityTimelineItem' => null, 'containerKey' => null])

@php
    /** @var RalphJSmit\Filament\Activitylog\Data\TimelineData $timelineData */

    $activityTimelineItems = $timelineData->getActivityTimelineItems();

    if ($parentActivityTimelineItem) {
        // Sort the items, so that the item that links to the parent activity timeline item goes first.
        $activityTimelineItems = $activityTimelineItems->sortBy(function (
            ActivityTimelineItem $activityTimelineItem,
        ) use ($parentActivityTimelineItem) {
            return $activityTimelineItem->activity->is($parentActivityTimelineItem->activity) ? 0 : 1;
        });
    }

    $isCompact = $timelineData->isCompact();
    $isSearchable = $timelineData->isSearchable();
    $maxHeight = $timelineData->getMaxHeight();
@endphp

<div x-data="{
    search: '',
}" class="fi-rjs-activitylog-timeline">
    @if ($timelineData->isSearchable() && $activityTimelineItems->isNotEmpty())
        <x-filament::input.wrapper :class="\Illuminate\Support\Arr::toCssClasses([
            'mb-5' => !$isCompact,
            'mb-4' => $isCompact,
            'fi-rjs-activitylog-timeline-search-wrp',
        ])">
            <x-filament::input type="text" x-model="search" :placeholder="__('filament-activitylog::translations.components.timeline.search.placeholder')"
                class="fi-rjs-activitylog-timeline-search-input" />
        </x-filament::input.wrapper>
    @endif

    @if ($activityTimelineItems->isNotEmpty())
        <ul @class([
            'flex flex-col',
            'mt-2.5' => !$isCompact,
            'mt-1' => $isCompact,
            'overflow-y-scroll pt-1.5 pb-3' => $maxHeight,
        ]) @style([
            "max-height: {$maxHeight}" . (is_numeric($maxHeight) ? 'px' : '') => $maxHeight,
        ])>
            @foreach ($activityTimelineItems as $activityTimelineItem)
                @php
                    $nextItem = $activityTimelineItems->get($loop->index + 1);
                    $nextItemIcon = $nextItem?->getIcon($isCompact);

                    $icon = $activityTimelineItem->getIcon($isCompact);

                    if (
                        $parentActivityTimelineItem &&
                        $parentActivityTimelineItem->activity->is($activityTimelineItem->activity) &&
                        $icon
                    ) {
                        $icon = null;
                    }

                    $iconColor = $activityTimelineItem->getIconColor();

                    $badge = $activityTimelineItem->getBadge();

                    $badgeColor = $activityTimelineItem->getBadgeColor();

                    $description = $activityTimelineItem->getDescription();

                    $batchTimelineData = $activityTimelineItem->getBatchTimelineData();
                @endphp
                <li wire:key="fi-rjs-activitylog-timeline-item.{{ $containerKey }}.{{ $parentActivityTimelineItem?->activity->getKey() }}.{{ $activityTimelineItem->activity->getKey() }}"
                    @class([
                        'relative flex flex-row items-top justify-between',
                        'gap-x-2' => !$isCompact,
                        'gap-x-2.5' => $isCompact,
                        'pb-4' => !$loop->last && !$isCompact,
                        'pb-2' => !$loop->last && $isCompact,
                        '-mb-1' => $icon && $isCompact,
                    ]) x-data
                    x-show="search === '' || $refs.description.textContent.toLowerCase().includes(search.toLowerCase())">
                    <div @class([
                        'flex-grow-0 flex-shrink-0 flex flex-row items-top justify-center',
                        'w-[31px] pt-1.5' => !$isCompact,
                        'w-6 pt-2.5' => $isCompact,
                    ])>
                        @unless ($loop->last)
                            <div @class([
                                'absolute flex justify-center',
                                'w-6 left-[3.6px] ' => !$isCompact,
                                'top-5' => !$icon && !$isCompact,
                                'top-8' => $icon && !$isCompact,
                                'bottom-0.5' => $icon && !$nextItem->getIcon() && !$isCompact,
                                'bottom-0' => !$icon && !$nextItem->getIcon() && !$isCompact,
                                'bottom-2.5' => $nextItem->getIcon() && !$isCompact,
                                'w-4 left-[4px] ' => $isCompact,
                                ' top-4' => !$icon && $isCompact,
                                ' top-6' => $icon && $isCompact,
                                ' bottom-0.5' => $icon && !$nextItem->getIcon() && $isCompact,
                                ' -bottom-1 ' => !$icon && !$nextItem->getIcon() && $isCompact,
                                ' bottom-3' => $nextItem->getIcon() && $isCompact,
                            ]) x-show="search === ''">
                                <div class="w-px bg-gray-300/70 dark:bg-gray-600/70"></div>
                            </div>
                        @endunless

                        @if ($parentActivityTimelineItem?->activity->is($activityTimelineItem->activity))
                            <div @class([
                                'h-px absolute bg-gray-300/70 dark:bg-gray-600/70',
                                'h-px w-[35px] top-[9px] left-[-31px]' => !$parentActivityTimelineItem->getIcon(),
                                'h-px w-[24px] top-[9.5px] -left-5' =>
                                    $parentActivityTimelineItem->getIcon() && !$isCompact,
                                'h-px w-[28px] top-[7.6px] -left-[22.5px]' =>
                                    $parentActivityTimelineItem->getIcon() && $isCompact,
                            ])></div>
                        @endif

                        @if ($icon)
                            <div @class([
                                'rounded-full flex flex-row items-center justify-center',
                                'w-7 h-7 -translate-y-2.5 bg-custom-50 text-custom-600 ring-1 ring-custom-500/90 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 ' => !$isCompact,
                                'w-6 h-6 -translate-y-3.5 bg-custom-50 dark:bg-custom-400/10 border border-custom-400/25' => $isCompact,
                            ]) @style([\Filament\Support\get_color_css_variables($iconColor ?? 'gray', shades: [50, 400, 500, 600])])>
                                <x-filament::icon :icon="$icon" :class="\Illuminate\Support\Arr::toCssClasses([
                                    'text-custom-500',
                                    'w-4 h-4' => !$isCompact,
                                    'w-4 h-4 ' => $isCompact,
                                ])" :style="\Filament\Support\get_color_css_variables(
                                    $iconColor ?? 'gray',
                                    shades: [500],
                                )" />
                            </div>
                        @else
                            <div @class([
                                '-translate-y-2.5 flex flex-row items-center justify-center',
                                'w-7 h-7' => !$isCompact,
                                'w-5 h-5' => $isCompact,
                            ])>
                                <div @class([
                                    'bg-custom-100 text-custom-600 ring-1 ring-custom-400/80 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 rounded-full',
                                    'w-2 h-2' => !$isCompact,
                                    'w-1.5 h-1.5' => $isCompact,
                                ]) @style([\Filament\Support\get_color_css_variables($iconColor ?? 'gray', shades: [100, 400, 600])])>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div @class([
                        'flex-grow text-sm text-gray-700 dark:text-gray-300',
                        'pb-2' => $isCompact,
                    ])>
                        @unless ($batchTimelineData)
                            <div x-ref="description">
                                <div
                                    class="[&_a]:after:content-['_↗'] [&_a]:underline [&_a]:underline-offset-4 [&_a]:text-primary-600 [&_a]:dark:text-primary-400">
                                    {{ $description }}
                                </div>

                                @if ($badge)
                                    <x-filament::badge :color="$badgeColor" class="!inline-flex">
                                        {{ $badge }}
                                    </x-filament::badge>
                                @endif
                            </div>
                        @endunless

                        @if (!$batchTimelineData && ($actions = $activityTimelineItem->getActions()))
                            <x-filament::actions :actions="$actions" class="mt-2" />
                        @endif

                        @if ($batchTimelineData)
                            <div class="-mt-1">
                                <div class="ml-4 relative">
                                    <x-filament-activitylog::timeline :timeline-data="$batchTimelineData" :parent-activity-timeline-item="$activityTimelineItem"
                                        :container-key="$containerKey" />
                                </div>
                            </div>
                        @endif
                    </div>

                    <div @class(['flex-grow-0 text-sm text-gray-700 dark:text-gray-600'])>
                        @unless ($parentActivityTimelineItem?->activity->is($activityTimelineItem->activity))
                            <time datetime="{{ ($time = $activityTimelineItem->getDateTime())->toDateTimeString() }}"
                                class="flex-none py-0.5 text-xs leading-5 text-gray-500 whitespace-nowrap" x-data
                                x-tooltip.raw="{{ $activityTimelineItem->getDateTimeFormatted() }}">
                                {{ $time->shortRelativeDiffForHumans() }}
                            </time>
                        @endunless
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <div class="flex flex-col items-center justify-center my-10">
            @if ($emptyStateIcon = $timelineData->getEmptyStateIcon())
                <div class="rounded-full bg-gray-100 p-[6.5px] dark:bg-gray-500/20">
                    <x-filament::icon :icon="$emptyStateIcon" class="h-[16px] w-[16px] text-gray-400 dark:text-gray-500" />
                </div>

                <h4 class="mt-2 text-base font-medium text-black dark:text-white">
                    {{ $timelineData->getEmptyStateHeading() }}
                </h4>

                @if ($emptyStateDescription = $timelineData->getEmptyStateDescription())
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-500">
                        {{ $emptyStateDescription }}
                    </p>
                @endif
            @endif
        </div>
    @endif
</div>
