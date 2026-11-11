@php
    $changeList = $changeList ?? null;
@endphp
**{{ __('Summary') }}:** {{ $task->summary }}{{ $changeList?->suffix('summary') }}<br>
@if (filled($task->description) || $changeList?->has('description'))
**{{ __('Description') }}:** {{ filled($task->description) ? $task->description : '-' }}{{ $changeList?->suffix('description') }}<br>
@endif
@if (filled($task->location?->name ?? $task->venue?->name) || $changeList?->has('venue_id'))
**{{ __('Venue') }}:** {{ $task->location?->name ?? $task->venue?->name ?? '-' }}{{ $changeList?->suffix('venue_id') }}<br>
@endif
@if ($task->due_at || $changeList?->has('due_at'))
**{{ __('Due') }}:** {{ $task->due_at ? localDate($task->due_at, config('app.timezone'))->isoFormat('ddd D MMM YYYY HH:mm') : '-' }}{{ $changeList?->suffix('due_at') }}<br>
@endif
@if ($changeList?->has('priority'))
**{{ __('Priority') }}:** {{ $task->priority->getLabel() }}{{ $changeList->suffix('priority') }}<br>
@endif
**{{ __('Status') }}:** {{ $task->status->getLabel() }}{{ $changeList?->suffix('status') }}
