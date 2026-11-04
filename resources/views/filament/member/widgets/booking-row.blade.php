@php
    use App\Enums\BookingAttendeeStatus;
    use App\Enums\BookingStatus;
    use App\Filament\Member\Resources\Bookings\BookingResource;
    use Filament\Support\Icons\Heroicon;

    $own = $booking->attendees->first()?->attendance?->status;
    $isLead = $booking->isLead(auth()->user());
    $showStatus ??= true;
    $showResponse ??= false;
    $warning ??= null;

    $start = localDate($booking->start_at, $booking->timezone);
    $end = localDate($booking->end_at, $booking->timezone);
    $time = $start->isSameDay($end)
        ? $start->isoFormat('HH:mm').' – '.$end->isoFormat('HH:mm')
        : $start->isoFormat('HH:mm').' – '.$end->isoFormat('ddd D MMM HH:mm');
    $needsAnswer = $showResponse && $booking->acceptsInvites() && in_array($own, BookingAttendeeStatus::outstanding(), true);
    $chip = 'inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-sm text-gray-700 dark:bg-white/10 dark:text-gray-300';
@endphp

<li class="relative flex flex-col gap-4 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center {{ $booking->isPast() ? 'opacity-60' : '' }}" wire:key="booking-{{ $booking->getKey() }}">
    <div class="min-w-0 flex-1 space-y-2">
        <p class="text-base font-semibold">
            <a href="{{ BookingResource::viewUrl($booking) }}" class="hover:underline after:absolute after:inset-0 after:content-['']">{{ $booking->activityTitle() }}</a>
            @if ($booking->audience)
                <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $booking->audience->name }}</span>
            @endif
            @if ($booking->status === BookingStatus::Tentative)
                <x-filament::badge class="ml-1 inline-flex align-middle" :color="$booking->status->getColor()" :icon="$booking->status->getIcon()">{{ $booking->status->getLabel() }}</x-filament::badge>
            @endif
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <span class="{{ $chip }}"><x-filament::icon :icon="Heroicon::OutlinedClock" class="size-4" />{{ $time }}</span>
            @if ($booking->venue)
                <span class="{{ $chip }}"><x-filament::icon :icon="Heroicon::OutlinedMapPin" class="size-4" />{{ $booking->venue->name }}</span>
            @endif
            @if ($booking->team)
                <span class="{{ $chip }}"><x-filament::icon :icon="Heroicon::OutlinedUserGroup" class="size-4" />{{ $booking->team->name }}</span>
            @endif
        </div>

        @if ($isLead || ($showStatus && $own))
            <div class="flex flex-wrap items-center gap-2">
                @if ($isLead)
                    <x-filament::badge :color="\App\Support\Palette::manager()" :icon="Heroicon::OutlinedStar">{{ __('Lead instructor') }}</x-filament::badge>
                @endif
                @if ($showStatus && $own)
                    <x-filament::badge :color="$own->getColor()" :icon="$own->getIcon()">{{ $own->getLabel() }}</x-filament::badge>
                @endif
            </div>
        @endif

        @if ($warning)
            <p class="text-sm text-amber-700 dark:text-amber-400">{{ $warning }}</p>
        @endif
    </div>

    @if ($needsAnswer)
        <div class="relative z-10 flex shrink-0 flex-col items-start gap-2 sm:items-end">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ $own === BookingAttendeeStatus::Tentative ? __('Please confirm if you can make it') : __('Can you help with this?') }}
            </p>
            <x-filament::button
                :color="$own === BookingAttendeeStatus::Tentative ? \App\Support\Palette::uncertain() : 'primary'"
                wire:click="mountAction('respond', { booking: '{{ $booking->getKey() }}' })"
            >
                {{ $own === BookingAttendeeStatus::Tentative ? __('Confirm') : __('Respond') }}
            </x-filament::button>
        </div>
    @endif
</li>
