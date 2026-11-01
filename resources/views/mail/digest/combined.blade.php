<x-mail::message>
@if ($outstanding !== null)
{{ trans_choice('You have :count new notification.|You have :count new notifications.', $outstanding, ['count' => $outstanding]) }}

@if ($unreadRemainder > 0)
{{ trans_choice('You also have :count unread notification from earlier.|You also have :count unread notifications from earlier.', $unreadRemainder, ['count' => $unreadRemainder]) }}
@endif

<x-mail::button :url="$inboxUrl">
{{ __('Open inbox') }}
</x-mail::button>
@endif

@if (is_array($rota))
# {{ __('Your rota') }}

@if ($rota['additions'] !== [])
**{{ __('Added') }}**

@foreach ($rota['additions'] as $line)
- {{ $line }}
@endforeach
@endif

@if ($rota['removals'] !== [])
**{{ __('Removed') }}**

@foreach ($rota['removals'] as $line)
- {{ $line }}
@endforeach
@endif

@if ($rota['remaining'] !== [])
**{{ __('Still on your rota') }}**

@foreach ($rota['remaining'] as $line)
- {{ $line }}
@endforeach
@endif
@endif

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
