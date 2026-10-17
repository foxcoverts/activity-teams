<x-mail::message>
# {{ $title }}

{{ $intro }}

@if ($changeList->summary())
<x-mail::panel>
@if ($changeNotice)
**{{ $changeNotice }}**

@endif
**{{ __('Changes') }}:** {{ $changeList->summary() }}
</x-mail::panel>
@endif

**{{ __('Activity') }}:** {{ $booking->activity->name }}{{ $changeList->suffix('activity_id') }}  
**{{ __('Venue') }}:** {{ $booking->venue->name }}{{ $changeList->suffix('venue_id') }}  
**{{ __('Group') }}:** {{ $booking->group_name }}{{ $changeList->suffix('group_name') }}  
<x-booking.schedule :booking="$booking" variant="mail" :changed="$changeList->scheduleLabels()" />
@if ($changeList->has('notes'))
**{{ __('Notes') }}:** {{ $booking->notes }} {{ __('(changed)') }}  
@endif
@if ($changeList->has('lead_instructor_id'))
**{{ __('Lead instructor') }}:** {{ $changeList->leadInstructorName() }} {{ __('(changed)') }}  
@endif
@if ($changeList->has('status'))
**{{ __('Status') }}:** {{ $changeList->statusLabel() }} {{ __('(changed)') }}  
@endif

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
