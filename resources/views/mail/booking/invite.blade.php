<x-mail::message>
# {{ __('You have been invited') }}

{{ __('You have been invited to help with a booking.') }}

**{{ __('Activity') }}:** {{ $booking->activity->name }}  
**{{ __('Venue') }}:** {{ $booking->venue->name }}  
**{{ __('Group') }}:** {{ $booking->group_name }}  
<x-booking.schedule :booking="$booking" variant="mail" />

<x-mail::button :url="$respondUrl">
{{ __('Respond') }}
</x-mail::button>

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
