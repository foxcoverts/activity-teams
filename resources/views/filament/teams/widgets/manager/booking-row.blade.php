@php
    use App\Enums\TentativeTriage;
    use App\Filament\Teams\Resources\Bookings\BookingResource;
    use Filament\Support\Icons\Heroicon;

    $start = localDate($booking->start_at, $booking->timezone);
    $end = localDate($booking->end_at, $booking->timezone);
    $time = $start->isSameDay($end)
        ? $start->isoFormat('HH:mm') . ' – ' . $end->isoFormat('HH:mm')
        : $start->isoFormat('HH:mm') . ' – ' . $end->isoFormat('ddd D MMM HH:mm');
    $chip =
        'inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-sm text-gray-700 dark:bg-white/10 dark:text-gray-300';
    $triageColor = $triage instanceof TentativeTriage ? $triage->color() : null;
@endphp

<li class="relative flex flex-col gap-4 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center"
    wire:key="manager-booking-{{ $booking->getKey() }}">
    <div class="relative min-w-0 flex-1 space-y-2">
        <p class="text-base font-semibold">
            <a href="{{ BookingResource::getUrl('view', ['record' => $booking]) }}"
                class="hover:underline after:absolute after:inset-0 after:z-0 after:content-['']">{{ $booking->activityTitle() }}</a>
            @if ($booking->audience)
                <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $booking->audience->name }}</span>
            @endif
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <span class="{{ $chip }}"><x-filament::icon :icon="Heroicon::OutlinedClock"
                    class="size-4" />{{ $time }}</span>
            @if ($booking->venue)
                <span class="{{ $chip }}"><x-filament::icon :icon="Heroicon::OutlinedMapPin"
                        class="size-4" />{{ $booking->venue->name }}</span>
            @endif
            @if ($triage instanceof TentativeTriage)
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-color-50 px-2.5 py-1 text-sm text-color-600 dark:bg-color-400/10 dark:text-color-400"
                    @style([
                        \Filament\Support\get_color_css_variables($triageColor, shades: [50, 400, 600]),
                    ])
                >
                    {{ $triage->getLabel() }}
                </span>
            @endif
        </div>
    </div>
</li>
