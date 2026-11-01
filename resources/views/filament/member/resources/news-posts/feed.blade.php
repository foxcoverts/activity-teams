@php
    use App\Filament\Member\Resources\NewsPosts\NewsPostResource;
    use Illuminate\Support\HtmlString;
@endphp

<div class="space-y-6">
    @if ($this->teams()->count() > 1 || $this->team instanceof \App\Models\Team)
        <nav class="news-team-filters flex flex-wrap items-center gap-2" aria-label="{{ __('Filter by team') }}">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Filter by team') }}</span>
            @foreach ($this->teams() as $item)
                @if ($this->team?->is($item))
                    <x-filament::badge :color="\App\Support\Palette::informative()">
                        {{ $item->name }}
                        <x-slot name="deleteButton" wire:click="clearTeamFilter" :label="__('All news')"></x-slot>
                    </x-filament::badge>
                @else
                    <x-filament::badge
                        tag="a"
                        :href="$this->teamUrl($item)"
                        :color="\App\Support\Palette::neutral()"
                    >
                        {{ $item->name }}
                    </x-filament::badge>
                @endif
            @endforeach
        </nav>
    @endif

    @forelse ($this->posts() as $post)
        @php
            $url = NewsPostResource::getUrl('view', ['team' => $post->team, 'record' => $post]);
            $user = auth()->user();
            $publishedAt = $post->published_at?->timezone($user?->timezone ?? config('app.timezone'));
            $description = implode(' · ', array_filter([
                $post->author?->name ? __('By :author', ['author' => $post->author->name]) : null,
                $publishedAt ? __('Posted :when', ['when' => $publishedAt->diffForHumans()]) : null,
            ]));
            $heading = new HtmlString(
                '<a href="'.e($url).'" class="hover:underline">'.e($post->title).'</a>'
            );
        @endphp

        <article>
            <x-filament::section
                :heading="$heading"
                :description="$description"
            >
                <x-slot name="afterHeader">
                    <x-filament::badge :color="\App\Support\Palette::neutral()">
                        {{ $post->team->name }}
                    </x-filament::badge>
                </x-slot>

                <p>{{ $post->excerpt() }}</p>
                <x-filament::link :href="$url">
                    {{ __('Read more...') }}
                </x-filament::link>
            </x-filament::section>
        </article>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No news has been published yet.') }}</p>
        </x-filament::section>
    @endforelse

    @if ($this->posts()->hasPages())
        <div>
            {{ $this->posts()->links() }}
        </div>
    @endif
</div>
