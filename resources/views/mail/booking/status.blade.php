<x-mail::message>
# {{ $title }}

{{ $intro }}

@if ($leadResponsibilityNotice)
<x-mail.panel tone="info">
**{{ __('You are the lead instructor') }}**

{{ __('You have been given lead-instructor responsibility for this booking.') }}
</x-mail.panel>
@elseif ($leadNeededNotice)
<x-mail.panel tone="warning">
**{{ __('Lead instructor needed') }}**

{{ __('This booking has no lead instructor.') }}
</x-mail.panel>
@elseif ($changeList->summary())
<x-mail.panel tone="neutral">
@if ($changeNotice)
**{{ $changeNotice }}**

@endif
**{{ __('Changes') }}:** {{ $changeList->summary() }}
</x-mail.panel>
@endif

**{{ __('Activity') }}:** {{ $booking->activity->name }}{{ $changeList->suffix('activity_id') }}  
**{{ __('Venue') }}:** {{ $booking->venue->name }}{{ $changeList->suffix('venue_id') }}  
**{{ __('Group') }}:** {{ $booking->group_name }}{{ $changeList->suffix('group_name') }}  
<x-booking.schedule :booking="$booking" variant="mail" :changed="$changeList->scheduleLabels()" />
@if ($changeList->has('notes'))
**{{ __('Notes') }}:** {{ $booking->notes }} {{ __('(changed)') }}  
@endif
@if ($changeList->has('lead_instructor_id') && ! $leadResponsibilityNotice && ! $leadNeededNotice)
**{{ __('Lead instructor') }}:** {{ $changeList->leadInstructorName() ?? '_'.__('none').'_' }} {{ __('(changed)') }}  
@endif
@if ($changeList->has('status'))
**{{ __('Status') }}:** {{ $changeList->statusLabel() }} {{ __('(changed)') }}  
@endif
@if (filled($leadNotes))
**{{ __('Lead instructor notes') }}:** {{ $leadNotes }}  
@endif

@if ($attendance && ! $attendance->needsAction())
<x-mail.response :status="$attendance->status">
{{ $attendance->status->responseSentence() }}
</x-mail.response>
@endif
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
