@php
    use App\Enums\TentativeTriage;
    use App\Support\Palette;

    $days = $bookings->groupBy(fn ($booking) => localDate($booking->start_at, $booking->timezone)->toDateString());
@endphp

<ul class="space-y-3">
    @foreach ($days as $dayBookings)
        @php
            $day = localDate($dayBookings->first()->start_at, $dayBookings->first()->timezone);
            $urgentReadyDay = isset($attention) && $dayBookings->contains(
                fn ($booking): bool => $attention->classify($booking) === TentativeTriage::ReadyToConfirm
                    && $attention->isWithinAtRiskWindow($booking),
            );
        @endphp
        <li
            @class([
                'manager-day-urgent-ready flex flex-col gap-4 rounded-xl p-4 sm:flex-row' => $urgentReadyDay,
                'flex flex-col gap-4 rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 sm:flex-row dark:bg-white/5 dark:ring-white/10' => ! $urgentReadyDay,
                'bg-color-50 ring-1 ring-color-600/25 dark:bg-color-400/10 dark:ring-color-400/30' => $urgentReadyDay,
            ])
            @style([
                \Filament\Support\get_color_css_variables(Palette::uncertain(), shades: [50, 400, 600]) => $urgentReadyDay,
            ])
            wire:key="manager-day-{{ $day->toDateString() }}">
            <div @class([
                'flex w-full shrink-0 flex-row items-baseline gap-1.5 border-b pb-3 sm:w-14 sm:border-b-0 sm:pb-0 sm:flex-col sm:items-center sm:gap-0 sm:self-start sm:text-center sm:leading-tight',
                'border-color-600/20 dark:border-color-400/20' => $urgentReadyDay,
                'border-gray-200 dark:border-white/10' => ! $urgentReadyDay,
            ])
                aria-hidden="true">
                <span class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $day->isoFormat('ddd') }}</span>
                <span class="text-2xl font-bold">{{ $day->isoFormat('D') }}</span>
                <span class="text-xs uppercase text-gray-500 dark:text-gray-400">{{ $day->isoFormat('MMM') }}</span>
            </div>

            <ul class="min-w-0 flex-1 divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($dayBookings as $booking)
                    @include('filament.teams.widgets.manager.booking-row', [
                        'booking' => $booking,
                        'attention' => $attention ?? null,
                        'urgentReadyDay' => $urgentReadyDay,
                        'triage' => isset($attention)
                            ? $attention->classify($booking)
                            : ($defaultTriage ?? null),
                    ])
                @endforeach
            </ul>
        </li>
    @endforeach
</ul>
