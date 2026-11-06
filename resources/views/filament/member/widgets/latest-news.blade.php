<x-filament-widgets::widget :class="$failed || !$post ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif ($post)
        @php
            $url = $post->memberUrl();
            $user = auth()->user();
            $publishedAt = $post->published_at?->timezone($user?->timezone ?? config('app.timezone'));
            $description = implode(
                ' · ',
                array_filter([
                    $post->author?->name ? __('By :author', ['author' => $post->author->name]) : null,
                    $publishedAt ? __('Posted :when', ['when' => $publishedAt->diffForHumans()]) : null,
                ]),
            );
            $heading = new \Illuminate\Support\HtmlString(
                '<a href="' . e($url) . '" class="hover:underline">' . e($post->title) . '</a>',
            );
        @endphp

        <h2 class="mb-3 text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('Latest news') }}</h2>

        <x-filament::section :heading="$heading" :description="$description" :icon="\Filament\Support\Icons\Heroicon::OutlinedNewspaper">
            <x-slot name="afterHeader">
                @if ($isNew)
                    <x-filament::badge :color="\App\Support\Palette::informative()">{{ __('New') }}</x-filament::badge>
                @endif
                <x-filament::badge :color="\App\Support\Palette::neutral()">{{ $post->team->name }}</x-filament::badge>
            </x-slot>

            <p>{{ $post->excerpt() }}</p>

            <div
                class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
                <x-filament::link :href="$url">{{ __('Read more...') }}</x-filament::link>
                <x-filament::link :href="$newsUrl ?? \App\Filament\Member\Resources\NewsPosts\NewsPostResource::getUrl('index')" :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowRight"
                    icon-position="after">{{ __('All news') }}</x-filament::link>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
