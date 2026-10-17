<x-mail::message>
# {{ $title }}

{{ $intro }}

**{{ __('Activity') }}:** {{ $booking->activity->name }}  
**{{ __('Venue') }}:** {{ $booking->venue->name }}  
**{{ __('Group') }}:** {{ $booking->group_name }}  
<x-booking.schedule :booking="$booking" variant="mail" />

@if ($attendance && ! $attendance->needsAction())
<x-mail.response :status="$attendance->status">
{{ $attendance->status->responseSentence() }}
</x-mail.response>
@endif
@if ($respondUrl)
<x-mail::button :url="$respondUrl">
{{ ($attendance && ! $attendance->needsAction()) || ! $booking->acceptsInvites() ? __('View response') : __('Respond') }}
</x-mail::button>
@endif

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
