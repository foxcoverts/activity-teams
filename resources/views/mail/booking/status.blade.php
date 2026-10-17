<x-mail::message>
# {{ $subject }}

{{ $intro }}

**{{ __('Activity') }}:** {{ $booking->activity->name }}  
**{{ __('Venue') }}:** {{ $booking->venue->name }}  
**{{ __('Group') }}:** {{ $booking->group_name }}  
**{{ __('Starts') }}:** {{ localDate($booking->start_at, $booking->timezone)->isoFormat('ddd D MMM YYYY HH:mm') }}

<x-mail::button :url="$url">
{{ __('View booking') }}
</x-mail::button>

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
