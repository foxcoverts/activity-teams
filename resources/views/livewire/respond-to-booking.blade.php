@php
    $panelClass = match ($response) {
        \App\Enums\BookingAttendeeStatus::Accepted => 'bg-sky-50 text-sky-900',
        \App\Enums\BookingAttendeeStatus::Tentative => 'bg-amber-50 text-amber-900',
        \App\Enums\BookingAttendeeStatus::Declined => 'bg-slate-100 text-slate-800',
        \App\Enums\BookingAttendeeStatus::NeedsAction => '',
    };

    $choices = [
        \App\Enums\BookingAttendeeStatus::Accepted->value => [__('Yes, I can help'), 'bg-sky-600'],
        \App\Enums\BookingAttendeeStatus::Tentative->value => [__('Maybe'), 'bg-amber-500'],
        \App\Enums\BookingAttendeeStatus::Declined->value => [__('I cannot make it'), 'bg-slate-600'],
    ];
@endphp

<div class="space-y-6 rounded-xl bg-white p-6 shadow">
    <h1 class="text-xl font-semibold">{{ __('Respond to booking') }}</h1>

    <dl class="space-y-2 text-sm">
        <div>
            <dt class="font-medium">{{ __('Activity') }}</dt>
            <dd>{{ $booking->activityTitle() }}</dd>
        </div>
        <div>
            <dt class="font-medium">{{ __('Audience') }}</dt>
            <dd>{{ $booking->audience?->name }}</dd>
        </div>
        <div>
            <dt class="font-medium">{{ __('Venue') }}</dt>
            <dd>{{ $booking->venue->name }}</dd>
        </div>
        @if (filled($booking->group_name))
            <div>
                <dt class="font-medium">{{ __('Group') }}</dt>
                <dd>{{ $booking->group_name }}</dd>
            </div>
        @endif
        <x-booking.schedule :booking="$booking" />
    </dl>

    <p><a href="{{ $this->bookingUrl() }}" class="text-sm text-sky-700 underline">{{ __('View booking') }}</a></p>

    @if ($sequenceStale)
        <p class="rounded-lg bg-amber-50 p-4 text-amber-950">
            {{ __('This booking has changed since the email you opened. Check the details above before you confirm.') }}
        </p>
    @endif

    @if ($warning = $this->leadWarning())
        <p class="rounded-lg bg-amber-50 p-4 text-amber-950">{{ $warning }}</p>
    @endif

    @if ($response !== \App\Enums\BookingAttendeeStatus::NeedsAction)
        <div class="space-y-2 rounded-lg p-4 {{ $panelClass }}">
            <p>{{ $response->responseSentence() }}</p>
            @if ($booking->acceptsInvites() && !$pendingChoice)
                <button type="button" wire:click="{{ $changing ? 'keepResponse' : 'changeResponse' }}"
                    class="text-sm underline">
                    {{ $changing ? __('Keep this response') : __('Change response') }}
                </button>
            @endif
        </div>
    @endif

    @if ($pendingChoice)
        <div class="space-y-3 rounded-lg bg-slate-50 p-4" x-data="{
            seconds: 5,
            timer: null,
            start() {
                this.timer = setInterval(() => {
                    this.seconds--;
                    if (this.seconds <= 0) {
                        this.stop();
                        $wire.respond(@js($pendingChoice));
                    }
                }, 1000);
            },
            stop() {
                clearInterval(this.timer);
            },
        }" x-init="start()">
            <p>{{ __('Saving “:response” in', ['response' => $this->pendingChoiceLabel()]) }} <span
                    x-text="seconds"></span> {{ __('seconds.') }}</p>
            <div class="flex flex-col gap-3">
                <button type="button" x-on:click="stop()" wire:click="respond('{{ $pendingChoice }}')"
                    class="rounded bg-sky-600 px-4 py-3 text-white">{{ __('Confirm now') }}</button>
                <button type="button" x-on:click="stop()" wire:click="cancelChoice"
                    class="text-sm underline">{{ __('Cancel') }}</button>
            </div>
        </div>
    @elseif ($booking->acceptsInvites() && ($response === \App\Enums\BookingAttendeeStatus::NeedsAction || $changing))
        @error('status')
            <p class="text-red-600">{{ $message }}</p>
        @enderror
        <div class="flex flex-col gap-3">
            @foreach ($choices as $value => [$label, $class])
                @if ($response->value !== $value)
                    <button type="button" wire:click="respond('{{ $value }}')"
                        class="rounded px-4 py-3 text-white {{ $class }}">{{ $label }}</button>
                @endif
            @endforeach
        </div>
    @endif
</div>
