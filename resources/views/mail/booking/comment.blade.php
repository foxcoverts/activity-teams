<x-mail::message>
# {{ __('New comment') }}

{{ __(':author commented on :activity.', [
    'author' => $author?->name ?? __('someone'),
    'activity' => $booking->activityTitle(),
]) }}

{{ $comment->body }}

<x-mail::button :url="$url">
{{ __('View comment') }}
</x-mail::button>

{{ __('Thanks') }},<br>
{{ config('app.name') }}

<x-mail::preferences :url="$manageNotificationsUrl ?? null">
{{ $manageNotificationsLabel ?? '' }}
</x-mail::preferences>
</x-mail::message>
