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
@if ($attendance->status === \App\Enums\BookingAttendeeStatus::Tentative && $choiceUrls !== [])

{{ __('Please update your response.') }}
@endif
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
