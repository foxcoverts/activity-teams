<x-mail::message>
# {{ $title }}

{{ $intro }}

**{{ __('Activity') }}:** {{ $booking->activityTitle() }}  
@if ($booking->venue)
**{{ __('Venue') }}:** {{ $booking->venue->name }}  
@endif
<x-booking.schedule :booking="$booking" variant="mail" />

@if ($bookingUrl)
<x-mail::button :url="$bookingUrl">
{{ __('Open booking') }}
</x-mail::button>
@endif

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
