<x-mail::message>
# {{ __('Attendance recorded') }}

**{{ __('Activity') }}:** {{ $booking->activity->name }}  
**{{ __('Venue') }}:** {{ $booking->venue->name }}  
**{{ __('Group') }}:** {{ $booking->group_name }}  
<x-booking.schedule :booking="$booking" variant="mail" />
@if (filled($attendance->comment))

**{{ __('Comment') }}:** {{ $attendance->comment }}
@endif

<x-mail.response :status="$attendance->status">
{{ __('A manager recorded your attendance as :status.', ['status' => $attendance->status->getLabel()]) }}
</x-mail.response>

@if ($choiceUrls !== [])
<x-mail.choices :urls="$choiceUrls" />
@elseif ($respondUrl)
<x-mail::button :url="$respondUrl">
{{ __('View response') }}
</x-mail::button>
@endif

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
