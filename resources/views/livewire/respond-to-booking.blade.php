<div class="space-y-6 rounded-xl bg-white p-6 shadow">
    <h1 class="text-xl font-semibold">{{ __('Respond to booking') }}</h1>

    <dl class="space-y-2 text-sm">
        <div><dt class="font-medium">{{ __('Activity') }}</dt><dd>{{ $booking->activity->name }}</dd></div>
        <div><dt class="font-medium">{{ __('Venue') }}</dt><dd>{{ $booking->venue->name }}</dd></div>
        <div><dt class="font-medium">{{ __('Group') }}</dt><dd>{{ $booking->group_name }}</dd></div>
        <div><dt class="font-medium">{{ __('Starts') }}</dt><dd>{{ localDate($booking->start_at, $booking->timezone)->isoFormat('ddd D MMM YYYY HH:mm') }}</dd></div>
    </dl>

    @if ($status)
        <p class="rounded bg-green-50 p-3 text-green-800">{{ __('Thanks, your response has been recorded.') }} ({{ $status }})</p>
    @else
        @error('status')
            <p class="text-red-600">{{ $message }}</p>
        @enderror
        <div class="flex flex-col gap-3">
            <button type="button" wire:click="respond('accepted')" class="rounded bg-sky-600 px-4 py-3 text-white">{{ __('Yes, I can help') }}</button>
            <button type="button" wire:click="respond('tentative')" class="rounded bg-amber-500 px-4 py-3 text-white">{{ __('Maybe') }}</button>
            <button type="button" wire:click="respond('declined')" class="rounded bg-slate-600 px-4 py-3 text-white">{{ __('I cannot make it') }}</button>
        </div>
    @endif
</div>
