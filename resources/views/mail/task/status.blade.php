<x-mail::message>
# {{ $title }}

{{ $intro }}

@if (($changeList ?? null)?->summary())
<x-mail.panel tone="neutral">
**{{ __('Changes') }}:** {{ $changeList->summary() }}
</x-mail.panel>
@endif

@include('mail.task.partials.details', ['changeList' => $changeList ?? null])

@if ($attendance && $attendance->status !== \App\Enums\TaskAttendeeStatus::NeedsAction)
<x-mail.response :status="$attendance->status">
{{ $attendance->status->responseSentence() ?: $attendance->status->getLabel() }}
</x-mail.response>
@endif

@if (($choiceUrls ?? []) !== [])
<x-mail.choices :urls="$choiceUrls" :prompt="__('Can you take this task?')" />
@elseif ($respondUrl ?? null)
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
