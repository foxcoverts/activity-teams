<x-mail::message>
# {{ $title ?? __('You have been invited') }}

{{ $intro ?? __('You have been invited to help with a booking.') }}

**{{ __('Activity') }}:** {{ $booking->activityTitle() }}<br>
**{{ __('Audience') }}:** {{ $booking->audience?->name }}  
**{{ __('Venue') }}:** {{ $booking->venue->name }}@if (filled($booking->group_name))  
**{{ __('Group') }}:** {{ $booking->group_name }}@endif  
<x-booking.schedule :booking="$booking" variant="mail" />

<x-mail.choices :urls="$choiceUrls" />

{{ __('Thanks') }},<br>
{{ config('app.name') }}

<x-mail::preferences :url="$manageNotificationsUrl ?? null">
{{ $manageNotificationsLabel ?? '' }}
</x-mail::preferences>
</x-mail::message>
