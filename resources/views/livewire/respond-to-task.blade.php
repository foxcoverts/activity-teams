@php
    $panelClass = match ($response) {
        \App\Enums\TaskAttendeeStatus::Accepted,
        \App\Enums\TaskAttendeeStatus::Completed => 'bg-sky-50 text-sky-900',
        \App\Enums\TaskAttendeeStatus::Tentative,
        \App\Enums\TaskAttendeeStatus::InProcess => 'bg-amber-50 text-amber-900',
        \App\Enums\TaskAttendeeStatus::Declined => 'bg-slate-100 text-slate-800',
        \App\Enums\TaskAttendeeStatus::NeedsAction => '',
    };

    $choices = [
        \App\Enums\TaskAttendeeStatus::Accepted->value => [__('Accept'), 'bg-sky-600'],
        \App\Enums\TaskAttendeeStatus::Declined->value => [__('Decline'), 'bg-slate-600'],
    ];
@endphp

<div class="space-y-6 rounded-xl bg-white p-6 shadow">
    <h1 class="text-xl font-semibold">{{ __('Respond to task') }}</h1>

    <dl class="space-y-2 text-sm">
        <div>
            <dt class="font-medium">{{ __('Summary') }}</dt>
            <dd>{{ $task->summary }}</dd>
        </div>
        @if (filled($task->description))
            <div>
                <dt class="font-medium">{{ __('Notes') }}</dt>
                <dd class="space-y-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5">
                    {!! \Illuminate\Support\Str::markdown((string) $task->description, [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ]) !!}
                </dd>
            </div>
        @endif
        @if (filled($task->location?->name ?? $task->venue?->name))
            <div>
                <dt class="font-medium">{{ __('Venue') }}</dt>
                <dd>{{ $task->location?->name ?? $task->venue?->name }}</dd>
            </div>
        @endif
        @if ($task->due_at)
            <div>
                <dt class="font-medium">{{ __('Due') }}</dt>
                <dd>{{ localDate($task->due_at, config('app.timezone'))->isoFormat('ddd D MMM YYYY HH:mm') }}</dd>
            </div>
        @endif
        <div>
            <dt class="font-medium">{{ __('Status') }}</dt>
            <dd>{{ $task->status->getLabel() }}</dd>
        </div>
    </dl>

    <p><a href="{{ $this->taskUrl() }}" class="text-sm text-sky-700 underline">{{ __('View task') }}</a></p>

    @if ($sequenceStale)
        <p class="rounded-lg bg-amber-50 p-4 text-amber-950">
            {{ __('This task has changed since the email you opened. Check the details above before you confirm.') }}
        </p>
    @endif

    @if ($response !== \App\Enums\TaskAttendeeStatus::NeedsAction)
        <div class="space-y-2 rounded-lg p-4 {{ $panelClass }}">
            <p>{{ $response->responseSentence() }}</p>
            @if ($this->acceptsResponses() && ! $pendingChoice)
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
    @elseif ($this->acceptsResponses() && ($response === \App\Enums\TaskAttendeeStatus::NeedsAction || $changing))
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
