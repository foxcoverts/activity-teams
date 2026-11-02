<div class="space-y-4 rounded-xl bg-white p-6 shadow">
    <h1 class="text-xl font-semibold">{{ $this->heading() }}</h1>
    <dl class="space-y-2 text-sm">
        <div><dt class="font-medium">{{ __('Activity') }}</dt><dd>{{ $booking->activityTitle() }}</dd></div>
        <div><dt class="font-medium">{{ __('Venue') }}</dt><dd>{{ $booking->venue->name }}</dd></div>
        <x-booking.schedule :booking="$booking" />
    </dl>
    <div class="space-y-3 rounded-lg bg-slate-100 p-4 text-center">
        <p class="text-lg">{{ __('Can you attend this event?') }}</p>
        <a href="{{ auth()->check() ? $this->bookingUrl() : $this->loginUrl() }}" class="inline-block rounded bg-sky-600 px-4 py-3 font-medium text-white">{{ __('View full details') }}</a>
        @guest
            <p class="text-sm">{{ __('Please log in to view full details and to RSVP.') }}</p>
        @endguest
    </div>
</div>
