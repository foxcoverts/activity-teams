<div class="space-y-4 rounded-xl bg-white p-6 shadow">
    <h1 class="text-xl font-semibold">{{ $booking->group_name }}</h1>
    <dl class="space-y-2 text-sm">
        <div><dt class="font-medium">{{ __('Activity') }}</dt><dd>{{ $booking->activity->name }}</dd></div>
        <div><dt class="font-medium">{{ __('Venue') }}</dt><dd>{{ $booking->venue->name }}</dd></div>
        <div><dt class="font-medium">{{ __('Status') }}</dt><dd>{{ $booking->status->value }}</dd></div>
        <div><dt class="font-medium">{{ __('Starts') }}</dt><dd>{{ localDate($booking->start_at, $booking->timezone)->isoFormat('ddd D MMM YYYY HH:mm') }}</dd></div>
        <div><dt class="font-medium">{{ __('Ends') }}</dt><dd>{{ localDate($booking->end_at, $booking->timezone)->isoFormat('HH:mm') }}</dd></div>
        @if ($booking->notes)
            <div><dt class="font-medium">{{ __('Notes') }}</dt><dd>{{ $booking->notes }}</dd></div>
        @endif
    </dl>
</div>
