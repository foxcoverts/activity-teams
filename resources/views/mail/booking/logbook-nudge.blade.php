<x-mail::message>
# {{ $title }}

{{ $intro }}

**{{ __('Activity') }}:** {{ $booking->activityTitle() }}  
@if ($booking->venue)
**{{ __('Venue') }}:** {{ $booking->venue->name }}  
@endif
<x-booking.schedule :booking="$booking" variant="mail" />

@if ($logbookUrl)
<x-mail::button :url="$logbookUrl">
{{ __('Open logbook') }}
</x-mail::button>
@endif

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
