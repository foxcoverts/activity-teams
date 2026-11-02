<x-mail::message>
# {{ __('Attendance recorded') }}

**{{ __('Activity') }}:** {{ $booking->activityTitle() }}<br>
**{{ __('Audience') }}:** {{ $booking->audience?->name }}  
**{{ __('Venue') }}:** {{ $booking->venue->name }}  
@if (filled($booking->group_name))
**{{ __('Group') }}:** {{ $booking->group_name }}  
@endif  
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

<x-mail::preferences :url="$manageNotificationsUrl ?? null">
{{ $manageNotificationsLabel ?? '' }}
</x-mail::preferences>
</x-mail::message>
