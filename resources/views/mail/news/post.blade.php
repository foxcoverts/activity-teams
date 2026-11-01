<x-mail::message>
# {{ $post->title }}

{{ $post->team?->name }} · {{ $post->author?->name }}
@if ($post->published_at)
 · {{ $post->published_at->timezone($recipient->timezone ?? config('app.timezone'))->format('j M Y, H:i') }}
@endif

{{ $post->excerpt() }}

<x-mail::button :url="$url">
{{ __('Read more') }}
</x-mail::button>

{{ __('Thanks') }},<br>
{{ config('app.name') }}

<x-mail::preferences :url="$manageNotificationsUrl ?? null">
{{ $manageNotificationsLabel ?? '' }}
</x-mail::preferences>
</x-mail::message>
