<x-mail::message>
# {{ __('Incident Report') }}

**{{ __('Team') }}:** {{ $report->teamName }}  
**{{ __('Date') }}:** {{ $report->dateTime->format('l, j F Y, H:i') }}

## {{ __('Location') }}

**{{ __('Name') }}:** {{ $report->locationName }}  
**{{ __('Description') }}:** {{ $report->locationDescription }}

**{{ __('Was anyone injured?') }}:** {{ $report->injured ? __('Yes') : __('No') }}

## {{ $report->injured ? __('Who was injured?') : __('Who was involved?') }}

@if ($report->hasInjuredName())
**{{ __('Name') }}:** {{ $report->injuredName }}  
@endif
@if ($report->hasInjuredDateOfBirth())
**{{ __('Date of birth') }}:** {{ $report->injuredDateOfBirth->format('j F Y') }}  
@endif
@if ($report->hasInjuredGender())
**{{ __('Gender') }}:** {{ $report->injuredGender->getLabel() }}  
@endif

**{{ __('Membership type') }}:** {{ $report->membershipType->getLabel() }}  
@if ($report->hasGroupName())
**{{ __('Group') }}:** {{ $report->groupName }}  
@endif
**{{ __('Contact name') }}:** {{ $report->contactName }}
@if ($report->hasContactPhone())
  
**{{ __('Contact phone') }}:** {{ $report->contactPhone }}
@endif
@if ($report->hasContactAddress())
  
**{{ __('Contact address') }}:** {{ $report->contactAddress }}
@endif

@if ($report->hasInjuries())
## {{ __('Injuries') }}

@foreach ($report->injuries as $injury)
- {{ $injury->getLabel() }}
@endforeach

**{{ __('Were emergency services called?') }}:** {{ $report->emergencyServices ? __('Yes') : __('No') }}  
**{{ __('Did the injured person go directly to hospital for treatment?') }}:** {{ $report->hospital ? __('Yes') : __('No') }}
@endif

@if ($report->isRIDDOR())
<x-mail::panel>
{{ __('This incident may need to be reported under RIDDOR.') }}
</x-mail::panel>
@endif

**{{ __('Was property damaged?') }}:** {{ $report->damaged ? __('Yes') : __('No') }}

## {{ __('What happened') }}

{{ $report->details }}

@if ($report->hasFirstAid())
## {{ __('First aid given') }}

{{ $report->firstAid }}
@endif

## {{ __('Reporter') }}

**{{ __('Name') }}:** {{ $report->reporterName }}  
**{{ __('Email') }}:** {{ $report->reporterEmail }}  
**{{ __('Phone') }}:** {{ $report->reporterPhone }}

</x-mail::message>
