<x-mail::message>
# {{ $title }}

{{ $intro }}

@include('mail.task.partials.details')

@if (($choiceUrls ?? []) !== [])
<x-mail.choices :urls="$choiceUrls" :prompt="__('Can you take this task?')" />
@elseif ($respondUrl ?? null)
<x-mail::button :url="$respondUrl">
{{ __('View response') }}
</x-mail::button>
@endif

{{ __('Thanks') }},<br>
{{ config('app.name') }}

<x-mail::preferences :url="$manageNotificationsUrl ?? null">
{{ $manageNotificationsLabel ?? '' }}
</x-mail::preferences>
</x-mail::message>
