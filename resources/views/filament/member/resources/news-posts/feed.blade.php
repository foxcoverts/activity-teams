@php
    use Illuminate\Support\HtmlString;
@endphp

<div class="space-y-6">
    @forelse ($this->posts() as $post)
        @php
            $url = $post->memberUrl();
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
