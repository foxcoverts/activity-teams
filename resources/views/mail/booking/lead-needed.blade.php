<x-mail::message>
# {{ __('Lead instructor needed') }}

@if ($previousLead)
{{ __(':name is no longer the lead instructor for this booking. The booking is tentative and needs a lead instructor.', ['name' => $previousLead->name]) }}
@else
{{ __('This booking is tentative and needs a lead instructor.') }}
@endif

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

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
