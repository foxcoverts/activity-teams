<x-mail::message>
# {{ $triage->getLabel() }}

{{ __('This tentative booking needs manager attention.') }}

**{{ __('Activity') }}:** {{ $booking->activityTitle() }}<br>
**{{ __('Audience') }}:** {{ $booking->audience?->name }}  
**{{ __('Venue') }}:** {{ $booking->venue->name }}@if (filled($booking->group_name))  
**{{ __('Group') }}:** {{ $booking->group_name }}@endif  
<x-booking.schedule :booking="$booking" variant="mail" />

@if ($bookingUrl)
<x-mail::button :url="$bookingUrl">
{{ __('View booking') }}
</x-mail::button>
@endif

@if ($managerUrl)
<x-mail::button :url="$managerUrl" color="gray">
{{ __('Open Manager') }}
</x-mail::button>
@endif

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
