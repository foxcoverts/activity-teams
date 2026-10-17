@props(['booking', 'variant' => 'list'])

@php
    $start = localDate($booking->start_at, $booking->timezone);
    $end = localDate($booking->end_at, $booking->timezone);
    $lines = $start->isSameDay($end)
        ? [
            __('Date') => $start->isoFormat('ddd D MMM YYYY'),
            __('Time') => $start->isoFormat('HH:mm').' - '.$end->isoFormat('HH:mm'),
        ]
        : [
            __('Start') => $start->isoFormat('ddd D MMM YYYY HH:mm'),
            __('End') => $end->isoFormat('ddd D MMM YYYY HH:mm'),
        ];
@endphp

@if ($variant === 'mail')
@foreach ($lines as $label => $value)
**{{ $label }}:** {{ $value }}  
@endforeach
@else
@foreach ($lines as $label => $value)
<div><dt class="font-medium">{{ $label }}</dt><dd>{{ $value }}</dd></div>
@endforeach
@endif
