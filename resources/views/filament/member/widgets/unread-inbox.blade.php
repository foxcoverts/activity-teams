<x-filament-widgets::widget :class="!$failed && $rows->isEmpty() ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif (!$rows->isEmpty())
        <x-filament::section :heading="__('Inbox')" :icon="\Filament\Support\Icons\Heroicon::OutlinedInbox">
            <x-slot name="afterHeader">
                <x-filament::badge>{{ $total }}</x-filament::badge>
            </x-slot>
            <ul class="space-y-2">
                @foreach ($rows as $notification)
                    <li class="relative flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-4 py-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
                        wire:key="unread-{{ $notification->id }}">
                        <a href="{{ \App\Filament\Member\Resources\Inbox\InboxResource::getUrl('view', ['record' => $notification]) }}"
                            class="text-sm hover:underline after:absolute after:inset-0 after:content-['']">
                            {{ $notification->data['body'] ?? '' }}
                        </a>
                        <x-filament::link tag="button" class="relative z-10 shrink-0"
                            wire:click="markRead('{{ $notification->id }}')"
                            size="sm">{{ __('Mark read') }}</x-filament::link>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                @if ($total > $rows->count())
                    <x-filament::button size="sm" color="gray"
                        wire:click="markShownRead({{ \Illuminate\Support\Js::from($rows->pluck('id')->all()) }})">
                        {{ __('Mark shown read') }}
                    </x-filament::button>
                @endif
                <x-filament::button size="sm" color="gray"
                    wire:click="markAllRead">{{ __('Mark all read') }}</x-filament::button>
                <x-filament::link :href="\App\Filament\Member\Resources\Inbox\InboxResource::getUrl('index')">{{ __('Open inbox') }}</x-filament::link>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
