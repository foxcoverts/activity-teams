@php
    $post = $this->getRecord();
    $user = auth()->user();
    $publishedAt = $post->published_at?->timezone($user?->timezone ?? config('app.timezone'));
    $description = implode(' · ', array_filter([
        $post->author?->name ? __('By :author', ['author' => $post->author->name]) : null,
        $publishedAt?->format('j M Y, H:i'),
    ]));
@endphp

<article class="news-article max-w-3xl">
    <x-filament::section :description="$description">
        <x-slot name="afterHeader">
            <x-filament::badge :color="\App\Support\Palette::neutral()">
                {{ $post->team->name }}
            </x-filament::badge>
        </x-slot>

        <div class="news-article-body fi-prose">
            {!! \Illuminate\Support\Str::markdown($post->body) !!}
        </div>
    </x-filament::section>
</article>
