@php
    $days = $bookings->groupBy(fn ($booking) => localDate($booking->start_at, $booking->timezone)->toDateString());
@endphp

<ul class="space-y-3">
    @foreach ($days as $dayBookings)
        @php($day = localDate($dayBookings->first()->start_at, $dayBookings->first()->timezone))
        <li class="flex flex-col gap-4 rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 sm:flex-row dark:bg-white/5 dark:ring-white/10"
            wire:key="manager-day-{{ $day->toDateString() }}">
            <div class="flex w-full shrink-0 flex-row items-baseline gap-1.5 border-b border-gray-200 pb-3 sm:w-14 sm:border-b-0 sm:pb-0 dark:border-white/10 sm:flex-col sm:items-center sm:gap-0 sm:self-start sm:text-center sm:leading-tight"
                aria-hidden="true">
                <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $day->isoFormat('ddd') }}</span>
                <span class="text-2xl font-bold">{{ $day->isoFormat('D') }}</span>
                <span class="text-xs uppercase text-gray-500 dark:text-gray-400">{{ $day->isoFormat('MMM') }}</span>
            </div>

            <ul class="min-w-0 flex-1 divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($dayBookings as $booking)
                    @include('filament.teams.widgets.manager.booking-row', [
                        'booking' => $booking,
                        'triage' => isset($attention)
                            ? $attention->classify($booking)
                            : ($defaultTriage ?? null),
                    ])
                @endforeach
            </ul>
        </li>
    @endforeach
</ul>
