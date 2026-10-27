<x-mail::message>
# {{ __('You have been invited') }}

{{ __('You have been invited to help with a booking.') }}

**{{ __('Activity') }}:** {{ $booking->activity->name }}  
**{{ __('Venue') }}:** {{ $booking->venue->name }}  
**{{ __('Group') }}:** {{ $booking->group_name }}  
<x-booking.schedule :booking="$booking" variant="mail" />

<x-mail.choices :urls="$choiceUrls" />

{{ __('Thanks') }},<br>
{{ config('app.name') }}

<x-mail::preferences :url="$manageNotificationsUrl ?? null">
{{ $manageNotificationsLabel ?? '' }}
</x-mail::preferences>
</x-mail::message>
