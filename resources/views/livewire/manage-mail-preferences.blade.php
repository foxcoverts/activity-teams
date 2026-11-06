<div class="space-y-6 rounded-xl bg-white p-6 shadow">
    <div>
        <h1 class="text-xl font-semibold">{{ $this->heading() }}</h1>
        @if ($booking && !$useGlobal)
            <p class="mt-2 text-sm text-slate-600">{{ $booking->relatedLabel() }}</p>
        @endif
    </div>

    @if ($booking)
        <div class="flex gap-2 text-sm">
            <button type="button" wire:click="$set('useGlobal', false)"
                class="rounded px-3 py-2 {{ $useGlobal ? 'bg-slate-100' : 'bg-sky-600 text-white' }}">
                {{ __('This booking') }}
            </button>
            <button type="button" wire:click="$set('useGlobal', true)"
                class="rounded px-3 py-2 {{ $useGlobal ? 'bg-sky-600 text-white' : 'bg-slate-100' }}">
                {{ __('All bookings') }}
            </button>
        </div>
    @endif

    @if ($saved)
        <p class="rounded-lg bg-sky-50 p-3 text-sm text-sky-950">{{ __('Notification settings saved.') }}</p>
    @endif

    <form wire:submit="save" class="space-y-4">
        @foreach ($this->channels() as $channel)
            @php
                $disabled = $channel->parent() instanceof \App\Enums\MailChannel && $this->masterOff();
            @endphp
            <div class="space-y-1">
                <label for="channel-{{ $channel->value }}"
                    class="block text-sm font-medium">{{ $channel->getLabel() }}</label>
                <p class="text-sm font-normal text-slate-600">{{ $channel->description() }}</p>
                <x-form.select id="channel-{{ $channel->value }}"
                    wire:model.live="preferences.{{ $channel->formKey() }}" :disabled="$disabled">
                    <option value="">{{ $this->inheritedLabel($channel) }}</option>
                    <option value="1">{{ __('On') }}</option>
                    <option value="0">{{ __('Off') }}</option>
                </x-form.select>
            </div>
        @endforeach

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="rounded bg-sky-600 px-4 py-2 text-white">{{ __('Save') }}</button>
            <button type="button" wire:click="resetOverride" class="rounded px-4 py-2 text-slate-700 underline">
                {{ $booking && !$useGlobal ? __('Reset this booking') : __('Reset to defaults') }}
            </button>
        </div>
    </form>

    <p>
        <a href="{{ $this->allSettingsUrl() }}"
            class="text-sm text-sky-700 underline">{{ __('View all notification settings') }}</a>
    </p>
</div>
